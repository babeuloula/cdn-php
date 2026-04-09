# CDN PHP (a lightweight CDN)

## Overview

This project is a lightweight CDN built with PHP.
It supports fetching, optimizing, caching and serving images dynamically while ensuring high flexibility and efficiency.

## Features

- **Domain Restriction:** Allows defining authorized domains via an environment variable (_ALLOWED_DOMAINS_).
- **On-the-Fly Image Processing:** Fetches images from a URL, compresses them lossless, and caches them.
- **WebP Support:** Converts images to WebP format if supported by the requesting client (GIFs are always served as GIF to preserve animation).
- **Animated GIF Support:** Preserves all frames of animated GIFs through resize operations.
- **Static Asset Support:** Fetches, optimizes, and serves JS/CSS/font files with proper cache headers:
  - **CSS & JS minification:** Automatically minifies `.css` and `.js` files to reduce file size.
  - **Font passthrough:** Serves `.woff`, `.woff2`, `.ttf`, `.eot`, `.otf` with long-term caching.
  - **SVG & ICO passthrough:** Serves `.svg` and `.ico` with long-term caching.
- **Configurable Storage:** Supports both local filesystem and S3-compatible storage.
- **Dynamic Image Resizing:** Resize images via query parameters:
  - `w` (width)
  - `h` (height)
  - `wu` (watermark URL)
  - `wp` (watermark position, default: center)
  - `ws` (watermark size percentage, default: 75%)
  - `wo` (watermark opacity percentage, default: 50%)
- **Smart Storage Structure:** Assets are stored based on query parameters.
- **Serverless Compatible:** Optimized to run in a serverless environment.
- **SSRF Protection:** Only domains listed in `ALLOWED_DOMAINS` can be fetched (applies to both source images and watermarks).
- **Fetch Hardening:** Configurable timeout, maximum file size, and redirect policy to prevent slow-loris, image-bomb, and SSRF-via-redirect attacks.
- **Force Re-fetch Protection:** Optional secret token required to bypass the cache (`FORCE_TOKEN`).

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
# Set to 1 only if your image origins serve via redirects (SSRF risk — see security notes)
FETCH_ALLOW_REDIRECTS=0

# Force re-fetch token (empty = no protection, set to a secret to require ?token=<value>)
FORCE_TOKEN=
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
- Optimize and compress it
- Convert it to WebP if supported
- Store it based on parameters
- Serve it with proper caching headers (`Cache-Control`, `ETag`, `Vary: Accept`)

### Static Assets

You can also use the CDN to serve and optimize your static assets:

```
# CSS (automatically minified)
https://cdn-php.loc/https://www.mysite.com/style.css

# JavaScript (automatically minified)
https://cdn-php.loc/https://www.mysite.com/app.js

# Fonts (served as-is with long-term caching)
https://cdn-php.loc/https://www.mysite.com/font.woff2

# SVG / ICO (served as-is with long-term caching)
https://cdn-php.loc/https://www.mysite.com/logo.svg
```

Supported extensions: `css`, `js`, `woff`, `woff2`, `ttf`, `eot`, `otf`, `svg`, `ico`, `xml`

CSS and JS files are automatically minified (comments and unnecessary whitespace removed) before being cached, reducing their size for faster delivery.

### Compression (GZIP)

**With the serverless setup (Bref / API Gateway):** GZIP compression is handled automatically by API Gateway via the `minimumCompressionSize` setting in `serverless.yml`. Responses larger than 1 KB are compressed transparently based on the client's `Accept-Encoding` header — no application-level changes needed.

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
