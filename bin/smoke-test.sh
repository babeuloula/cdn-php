#!/usr/bin/env bash

# HTTP smoke test for a running CDN PHP instance (local, dev or prod).
#
# Usage:
#   BASE_URL=https://cdn.example.com IMAGE_URL=example.com/path/image.jpg bin/smoke-test.sh
#
# Required:
#   BASE_URL       CDN base URL, without trailing slash
#   IMAGE_URL      JPEG/PNG source on an allowed domain (at least 300px wide and high), without scheme
# Optional (cases are skipped when empty):
#   WATERMARK_URL  Watermark image on an allowed domain, without scheme
#   CSS_URL, JS_URL, SVG_URL, FONT_URL  Static assets on an allowed domain, without scheme
#   AVIF_ENABLED   Set to false when AVIF is disabled on the target (WebP is then expected)
#   REPORT_DIR     Where to write the report (default: build)
#
# Every image request carries a unique `v` param, so the CDN really processes the image
# instead of serving the stored cache. The report only contains stable values (status,
# content type, dimensions) so two runs can be compared with `diff`.

set -u

BASE_URL="${BASE_URL:?BASE_URL is required}"
BASE_URL="${BASE_URL%/}"
IMAGE_URL="${IMAGE_URL:?IMAGE_URL is required}"
WATERMARK_URL="${WATERMARK_URL:-}"
CSS_URL="${CSS_URL:-}"
JS_URL="${JS_URL:-}"
SVG_URL="${SVG_URL:-}"
FONT_URL="${FONT_URL:-}"
REPORT_DIR="${REPORT_DIR:-build}"
AVIF_ENABLED="${AVIF_ENABLED:-true}"

strip_scheme() {
    local url="$1"
    url="${url#https://}"
    echo "${url#http://}"
}

IMAGE_URL="$(strip_scheme "$IMAGE_URL")"
WATERMARK_URL="$(strip_scheme "$WATERMARK_URL")"
IMAGE_DOMAIN="${IMAGE_URL%%/*}"

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
# Inside the project so the PHP container can read the downloaded files
WORK_DIR=".cache/smoke"
RUN_ID="smoke$(date +%s)"
REPORT_FILE="${REPORT_DIR}/smoke-$(date +%Y%m%d-%H%M%S).txt"

cd "$ROOT_DIR"
rm -rf "$WORK_DIR"
mkdir -p "$WORK_DIR" "$REPORT_DIR"
: > "$REPORT_FILE"

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[0;33m'
RESET='\033[0m'

PASSED=0
FAILED=0
SKIPPED=0
CASE_FAILED=0

# Last response
STATUS=""
HEADERS_FILE=""
BODY_FILE=""

# request <name> <path> [curl options...]
request() {
    local name="$1" path="$2"
    shift 2
    HEADERS_FILE="${WORK_DIR}/${name}.headers"
    BODY_FILE="${WORK_DIR}/${name}.body"
    STATUS="$(curl -sS -o "$BODY_FILE" -D "$HEADERS_FILE" -w '%{http_code}' "$@" "${BASE_URL}/${path}" 2>/dev/null)"
    STATUS="${STATUS:-000}"
}

header() {
    grep -i "^$1:" "$HEADERS_FILE" | tail -1 | cut -d' ' -f2- | tr -d '\r'
}

image_size() {
    DISABLE_TTY=1 docker/exec php -r '$s = @getimagesize($argv[1]); echo false === $s ? "invalid" : $s[0] . "x" . $s[1];' "$1" 2>/dev/null | tail -1
}

file_size() {
    wc -c < "$1" | tr -d ' '
}

start_case() {
    CASE_FAILED=0
    printf '%s\n' "$1"
}

check() {
    local label="$1" actual="$2" expected="$3"
    if [ "$actual" = "$expected" ]; then
        printf "  ${GREEN}✅ %s${RESET} (%s)\n" "$label" "$actual"
    else
        printf "  ${RED}❌ %s${RESET}: expected '%s', got '%s'\n" "$label" "$expected" "$actual"
        CASE_FAILED=1
    fi
}

# check_match <label> <actual> <glob pattern>
check_match() {
    local label="$1" actual="$2" pattern="$3"
    # shellcheck disable=SC2254
    case "$actual" in
        $pattern) printf "  ${GREEN}✅ %s${RESET} (%s)\n" "$label" "$actual" ;;
        *)
            printf "  ${RED}❌ %s${RESET}: expected '%s', got '%s'\n" "$label" "$pattern" "$actual"
            CASE_FAILED=1
            ;;
    esac
}

check_present() {
    local label="$1" value="$2"
    if [ -n "$value" ]; then
        printf "  ${GREEN}✅ %s${RESET} (%s)\n" "$label" "$value"
    else
        printf "  ${RED}❌ %s${RESET}: missing\n" "$label"
        CASE_FAILED=1
    fi
}

end_case() {
    local name="$1" summary="$2"
    echo "${name} | ${summary}" >> "$REPORT_FILE"
    if [ "$CASE_FAILED" -eq 0 ]; then
        PASSED=$((PASSED + 1))
    else
        FAILED=$((FAILED + 1))
    fi
}

skip_case() {
    printf "%s\n  ${YELLOW}⏭  skipped${RESET} (%s not set)\n" "$1" "$2"
    echo "$1 | skipped" >> "$REPORT_FILE"
    SKIPPED=$((SKIPPED + 1))
}

# image_case <name> <query> <accept> <expected content type> <expected dimensions glob>
image_case() {
    local name="$1" query="$2" accept="$3" expected_type="$4" expected_size="$5"
    start_case "$name"
    request "$name" "${IMAGE_URL}?v=${RUN_ID}${query:+&$query}" -H "Accept: ${accept}"
    check "status" "$STATUS" "200"
    check "content-type" "$(header Content-Type)" "$expected_type"
    check "vary" "$(header Vary)" "Accept"
    local size
    size="$(image_size "$BODY_FILE")"
    check_match "dimensions" "$size" "$expected_size"
    end_case "$name" "${STATUS} | $(header Content-Type) | ${size}"
}

# error_case <name> <path> <expected status> [curl options...]
error_case() {
    local name="$1" path="$2" expected="$3"
    shift 3
    start_case "$name"
    request "$name" "$path" "$@"
    check "status" "$STATUS" "$expected"
    end_case "$name" "$STATUS"
}

# static_case <name> <source url> <passthrough|minified>
static_case() {
    local name="$1" source="$2" mode="$3"
    source="$(strip_scheme "$source")"
    start_case "$name"
    request "$name" "${source}?v=${RUN_ID}"
    check "status" "$STATUS" "200"
    check_present "content-type" "$(header Content-Type)"
    check_present "cache-control" "$(header Cache-Control)"

    local original="${WORK_DIR}/${name}.original"
    curl -sS -o "$original" "https://${source}" 2>/dev/null
    local served_size original_size
    served_size="$(file_size "$BODY_FILE")"
    original_size="$(file_size "$original")"
    if [ "$mode" = "passthrough" ]; then
        check "identical to source" "$(cksum < "$BODY_FILE")" "$(cksum < "$original")"
    elif [ "$served_size" -le "$original_size" ] && [ "$served_size" -gt 0 ]; then
        printf "  ${GREEN}✅ minified${RESET} (%s <= %s bytes)\n" "$served_size" "$original_size"
    else
        printf "  ${RED}❌ minified${RESET}: served %s bytes, source %s bytes\n" "$served_size" "$original_size"
        CASE_FAILED=1
    fi
    end_case "$name" "${STATUS} | $(header Content-Type) | ${mode}"
}

echo "CDN smoke test against ${BASE_URL} (run ${RUN_ID})"
echo

# --- Routing and validation -------------------------------------------------

start_case "welcome"
request "welcome" ""
check "status" "$STATUS" "200"
end_case "welcome" "$STATUS"

error_case "method-not-allowed" "${IMAGE_URL}" "405" -X POST
error_case "domain-not-allowed" "smoke-not-allowed.invalid/image.jpg" "403"
error_case "extension-not-supported" "${IMAGE_DOMAIN}/smoke.exe" "400"
error_case "file-not-found" "${IMAGE_DOMAIN}/${RUN_ID}-missing.jpg?v=${RUN_ID}" "404"

# --- Images -----------------------------------------------------------------

start_case "image-original"
request "image-original" "${IMAGE_URL}?v=${RUN_ID}" -H "Accept: image/jpeg"
check "status" "$STATUS" "200"
check "content-type" "$(header Content-Type)" "image/jpeg"
check_present "cache-control" "$(header Cache-Control)"
check_present "etag" "$(header ETag)"
check_present "last-modified" "$(header Last-Modified)"
check_present "access-control-allow-origin" "$(header Access-Control-Allow-Origin)"
check_present "x-dominant-color" "$(header X-Dominant-Color)"
ORIGINAL_ETAG="$(header ETag)"
ORIGINAL_SIZE="$(image_size "$BODY_FILE")"
check_present "dimensions" "$(echo "$ORIGINAL_SIZE" | grep -v invalid)"
end_case "image-original" "${STATUS} | $(header Content-Type) | ${ORIGINAL_SIZE}"

start_case "image-cached"
request "image-cached" "${IMAGE_URL}?v=${RUN_ID}" -H "Accept: image/jpeg"
check "status" "$STATUS" "200"
check_present "etag" "$(header ETag)"
check "same etag" "$(header ETag)" "$ORIGINAL_ETAG"
end_case "image-cached" "$STATUS"

start_case "image-not-modified"
request "image-not-modified" "${IMAGE_URL}?v=${RUN_ID}" -H "Accept: image/jpeg" -H "If-None-Match: ${ORIGINAL_ETAG}"
check "status" "$STATUS" "304"
end_case "image-not-modified" "$STATUS"

image_case "image-width" "w=200" "image/jpeg" "image/jpeg" "200x[0-9]*"
image_case "image-height" "h=200" "image/jpeg" "image/jpeg" "[0-9]*x200"
image_case "image-crop" "w=200&h=100" "image/jpeg" "image/jpeg" "200x100"
image_case "image-webp" "w=200" "image/webp,image/*" "image/webp" "200x[0-9]*"
if [ "$AVIF_ENABLED" = "true" ]; then
    image_case "image-avif" "w=200" "image/avif,image/webp,image/*" "image/avif" "200x[0-9]*"
else
    image_case "image-avif-disabled" "w=200" "image/avif,image/webp,image/*" "image/webp" "200x[0-9]*"
fi

if [ -n "$WATERMARK_URL" ]; then
    # The watermark must not change the image dimensions
    WATERMARK_EXPECTED_SIZE="$ORIGINAL_SIZE"
    [ "$ORIGINAL_SIZE" != "invalid" ] || WATERMARK_EXPECTED_SIZE="<same as image-original>"
    image_case "image-watermark" "wu=${WATERMARK_URL}&wp=bottom-right&ws=30&wo=80" "image/jpeg" "image/jpeg" "$WATERMARK_EXPECTED_SIZE"
else
    skip_case "image-watermark" "WATERMARK_URL"
fi

# --- Static assets ----------------------------------------------------------

run_static_case() {
    local name="$1" source="$2" mode="$3" variable="$4"
    if [ -n "$source" ]; then
        static_case "$name" "$source" "$mode"
    else
        skip_case "$name" "$variable"
    fi
}

run_static_case "static-css" "$CSS_URL" "minified" "CSS_URL"
run_static_case "static-js" "$JS_URL" "minified" "JS_URL"
run_static_case "static-svg" "$SVG_URL" "passthrough" "SVG_URL"
run_static_case "static-font" "$FONT_URL" "passthrough" "FONT_URL"

# --- Summary ----------------------------------------------------------------

echo
printf "${GREEN}%d passed${RESET}, ${RED}%d failed${RESET}, ${YELLOW}%d skipped${RESET}\n" "$PASSED" "$FAILED" "$SKIPPED"
echo "Report: ${REPORT_FILE}"

[ "$FAILED" -eq 0 ]
