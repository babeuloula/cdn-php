# CDN PHP (a lightweight CDN)

## Overview

This project is a lightweight CDN built with PHP.
It supports fetching, optimizing, caching and serving images dynamically while ensuring high flexibility and efficiency.

## Features

- **Domain Restriction:** Allows defining authorized domains via an environment variable (_ALLOWED_DOMAINS_).
- **On-the-Fly Image Processing:** Fetches images from a URL, compresses them lossless, and caches them.
- **WebP Support:** Converts images to WebP format if supported by the requesting client.
- **Configurable Storage:** Supports both local filesystem and S3-compatible storage.
- **Dynamic Image Resizing:** Resize images via query parameters:
  - `w` (width)
  - `h` (height)
  - `wu` (watermark URL)
  - `wp` (watermark position, default: center)
  - `ws` (watermark size percentage, default: 75%)
  - `wo` (watermark opacity percentage, default: 50%)
- **Smart Storage Structure:** Images are stored based on query parameters.
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

STORAGE_TYPE=local # "local" or "s3"

# Local storage configuration
STORAGE_PATH=/var/task/.cache/driver/local

# S3 storage configuration (if STORAGE_TYPE=s3)
S3_BUCKET=my-bucket
S3_ENDPOINT=https://s3.amazonaws.com
S3_REGION=fr-par
S3_ACCESS_KEY=your-access-key
S3_SECRET_KEY=your-secret-key

# Cache (in seconds)
CACHE_TTL=31536000

# Logging
LOG_STREAM=/srv/.cache/log/cdn-php.log
LOG_LEVEL=debug

# Compression
IMAGE_COMPRESSION=75
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

You can fetch an optimized image by calling:

```
https://cdn-php.loc/https://www.mysite.com/image.png?w=200&h=200
```

The CDN will:
- Fetch the image from www.mysite.com
- Optimize and compress it
- Convert it to WebP if supported
- Store it based on parameters
- Serve it with proper caching headers

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

## Future works

- [ ] Create a Dockerfile for serverless functions
- [ ] Add a CLI with [Silly](https://github.com/mnapoli/silly)
  - [ ] Write a command to clear CDN cache folder
  - [ ] Write a command to clear all CDN images
  - [ ] Write a command to display some stats

## License

This project is open-source and available under the MIT License.
