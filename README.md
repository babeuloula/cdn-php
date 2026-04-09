# CDN PHP (a lightweight CDN)

## Overview

This project is a lightweight CDN built with PHP.
It supports fetching, optimizing, caching, and serving images and static assets dynamically while ensuring high flexibility and efficiency.

## Features

- **Domain Restriction:** Allows defining authorized domains via an environment variable (`ALLOWED_DOMAINS`).
- **On-the-Fly Image Processing:** Fetches images from a URL, compresses them, and caches them.
- **WebP & AVIF Support:** Converts images to WebP or AVIF format based on the client's `Accept` header (AVIF takes priority).
- **Animated GIF Support:** Preserves all frames of animated GIFs through resize operations; converts to animated WebP on demand.
- **EXIF Stripping:** Automatically removes EXIF metadata (GPS, device model…) from processed images to protect user privacy.
- **Dominant Color Header:** Returns an `X-Dominant-Color: #rrggbb` header on image responses for use as a placeholder while the image loads.
- **Static Asset Support:** Fetches, optimizes, and serves static files with proper cache headers:
  - **CSS & JS minification:** Automatically minifies `.css` and `.js` files.
  - **JSON minification:** Minifies `.json` and `.webmanifest` files.
  - **Font passthrough:** Serves `.woff`, `.woff2`, `.ttf`, `.eot`, `.otf` with long-term caching.
  - **SVG & ICO passthrough:** Serves `.svg` and `.ico` with long-term caching.
  - **Other passthroughs:** Serves `.xml`, `.txt`, `.map`, `.wasm` as-is with long-term caching.
- **Signed URLs:** Optional HMAC-SHA256 URL signing with expiration (`SIGNATURE_SECRET`). When enabled, requests must carry `?expires=<timestamp>&sig=<hmac>`.
- **SSRF Protection:** Blocks requests targeting private/reserved IP ranges (loopback, link-local, RFC 1918, etc.) in addition to domain allowlisting.
- **Fetch Hardening:** Configurable timeout, maximum file size, and redirect policy to prevent slow-loris, image-bomb, and SSRF-via-redirect attacks.
- **Force Re-fetch Protection:** Optional secret token required to bypass the cache (`FORCE_TOKEN`).
- **Configurable Storage:** Supports both local filesystem and S3-compatible storage.
- **Dynamic Image Resizing:** Resize images via query parameters:
  - `w` (width)
  - `h` (height)
  - `wu` (watermark URL)
  - `wp` (watermark position, default: center)
  - `ws` (watermark size percentage, default: 75%)
  - `wo` (watermark opacity percentage, default: 50%)
- **Smart Storage Structure:** Assets are stored based on query parameters for deterministic cache keys.
- **Serverless Compatible:** Optimized to run in a serverless environment.

## Serverless

### With serverless framework and bref.sh

```bash
# dev
make deploy
make remove

# prod
make deploy-prod
make remove-prod
```

### Upload your own ZIP

You can download the release ZIP file and upload it directly to your serverless function.
Or you can create your own release ZIP with:

```bash
make build
```

The build is available in: `build/latest.zip`.

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/babeuloula/cdn-php.git
cd cdn-php
```

### 2. Install the project

```bash
composer install
```

### 3. Set up environment variables

Copy `.env` to `.env.local` and configure your settings:

```bash
cp .env .env.local
```

Edit .env.local to match your setup:

```
APP_DEBUG=0

ALLOWED_DOMAINS=mysite.com,another-site.com
DOMAINS_ALIASES=another-site.com/secret-images=another

STORAGE_DRIVER=local # "local" or "s3"

# Local storage configuration
STORAGE_PATH=/var/task/.cache/driver/local

# S3 storage configuration (if STORAGE_DRIVER=s3)
S3_BUCKET=my-bucket
S3_ENDPOINT=https://s3.amazonaws.com
S3_REGION=fr-par
S3_ACCESS_KEY=your-access-key
S3_SECRET_KEY=your-secret-key

# Cache (in seconds)
CACHE_TTL=31536000

# Logging
LOG_STREAM=php://stderr
LOG_LEVEL=info

# Compression
IMAGE_COMPRESSION=75

# HTTP fetch (timeout in seconds, max size in bytes)
FETCH_TIMEOUT=10
FETCH_MAX_SIZE=52428800
# Set to 1 only if your image origins serve via redirects (SSRF risk - see security notes)
FETCH_ALLOW_REDIRECTS=0

# Force re-fetch token (empty = no protection, set to a secret to require ?token=<value>)
FORCE_TOKEN=

# URL signing secret (empty = disabled; when set, all requests must carry ?expires=<ts>&sig=<hmac>)
SIGNATURE_SECRET=
```

## Running with Docker

Ensure you have Docker and Docker Compose installed.

```bash
make install
```

After answering the questions (with default options), the service will be available at:

```
https://cdn-php.loc
```

## Usage

### Images

You can fetch an optimized image by calling:

```
https://cdn-php.loc/https://www.mysite.com/image.png?w=200&h=200
```

The CDN will:
- Fetch the image from www.mysite.com
- Strip EXIF metadata
- Optimize and compress it
- Convert it to AVIF or WebP if the client supports it
- Store it based on parameters
- Serve it with proper caching headers (`Cache-Control`, `ETag`, `Vary: Accept`)
- Add `X-Dominant-Color: #rrggbb` for use as a CSS placeholder

### Static Assets

You can also use the CDN to serve and optimize your static assets:

```
# CSS (automatically minified)
https://cdn-php.loc/https://www.mysite.com/style.css

# JavaScript (automatically minified)
https://cdn-php.loc/https://www.mysite.com/app.js

# JSON / Web App Manifest (automatically minified)
https://cdn-php.loc/https://www.mysite.com/manifest.json
https://cdn-php.loc/https://www.mysite.com/app.webmanifest

# Fonts (served as-is with long-term caching)
https://cdn-php.loc/https://www.mysite.com/font.woff2

# SVG / ICO (served as-is with long-term caching)
https://cdn-php.loc/https://www.mysite.com/logo.svg

# Other passthroughs
https://cdn-php.loc/https://www.mysite.com/robots.txt
https://cdn-php.loc/https://www.mysite.com/app.js.map
https://cdn-php.loc/https://www.mysite.com/module.wasm
```

Supported extensions: `css`, `js`, `woff`, `woff2`, `ttf`, `eot`, `otf`, `svg`, `ico`, `xml`, `json`, `webmanifest`, `txt`, `map`, `wasm`

### Signed URLs

When `SIGNATURE_SECRET` is set, every CDN request must carry a valid HMAC-SHA256 signature. This prevents anyone from constructing arbitrary CDN URLs directly – only your backend can generate valid ones.

**How it works:**

1. Your backend generates a signed URL and injects it into the HTML.
2. The browser fetches the CDN URL (with the signature).
3. The CDN verifies the signature before serving the asset.

**What gets signed:**

The signature covers the **source URL** (the image/asset origin, without CDN params) and the expiry timestamp:

```
HMAC-SHA256( "<source_url>:<expires>", SIGNATURE_SECRET )
```

**PHP helper (in your application backend):**

```php
function cdnUrl(
    string $cdnBase,
    string $sourceUrl,
    int    $ttl = 3600,
    array  $params = [],
): string {
    $expires = time() + $ttl;
    $sig     = hash_hmac('sha256', $sourceUrl . ':' . $expires, $_ENV['SIGNATURE_SECRET']);

    return $cdnBase . '/' . $sourceUrl . '?' . http_build_query(
        array_merge($params, ['expires' => $expires, 'sig' => $sig])
    );
}
```

**Usage in a Twig template (for example):**

```php
// In your controller
$imageUrl = cdnUrl(
    cdnBase:   'https://cdn.mysite.com',
    sourceUrl: 'https://www.mysite.com/uploads/photo.jpg',
    ttl:       3600,           // link valid for 1 hour
    params:    ['w' => 800, 'h' => 600],
);
```

```html
<!-- In your template -->
<img src="{{ imageUrl }}" alt="Photo">
```

This produces a URL like:

```
https://cdn.mysite.com/https://www.mysite.com/uploads/photo.jpg
    ?w=800&h=600&expires=1714000000&sig=a3f2c1...
```

**Error responses:**

| Situation                          | HTTP status     |
|------------------------------------|-----------------|
| `sig` missing or incorrect         | `403 Forbidden` |
| `expires` timestamp is in the past | `410 Gone`      |

> **Important:** `SIGNATURE_SECRET` must be kept server-side only. Never expose it in front-end code or public repositories.

### Compression (GZIP)

**With the serverless setup (Bref / API Gateway):** API Gateway handles GZIP compression automatically via the `minimumCompressionSize` setting in `serverless.yml`. Responses larger than 1 KB are compressed transparently based on the client's `Accept-Encoding` header - no application-level changes needed.

**Without serverless (Docker, Nginx, Apache…):** The CDN itself does not add `Content-Encoding: gzip` headers. You must enable compression at the web server or reverse-proxy level:

- **Nginx:** `gzip on; gzip_types text/css application/javascript font/woff2 image/svg+xml;`
- **Apache:** enable `mod_deflate` with the equivalent `AddOutputFilterByType` directive
- **Caddy:** compression is enabled by default

### Force re-fetch

To bypass the cache and re-fetch the source image:

```
https://cdn-php.loc/https://www.mysite.com/image.png?force=true
```

If `FORCE_TOKEN` is configured, the token must be provided:

```
https://cdn-php.loc/https://www.mysite.com/image.png?force=true&token=<your-token>
```

## Running Tests

```bash
# Execute PHPCS fixer
make fix-phpcs

# Execute PHPCS
make test-phpcs

# Execute PHPStan
make test-phpstan

# Execute PHP Mess Detector
make test-phpmd

# Execute PHPUnit
make test-phpunit

# Check CVE for vendor dependencies
make test-security

# Execute tests suite
make test
```

## License

This project is open-source and available under the MIT License.
