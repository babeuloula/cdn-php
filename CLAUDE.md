# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A lightweight PHP CDN for dynamic image processing and serving, deployed as AWS Lambda via the Bref framework. It fetches source images from remote URLs, applies transformations (resize, compress, WebP conversion, watermarking) using ImageMagick, caches results in local or S3 storage, and returns them with proper HTTP cache headers.

## Commands

All commands run inside Docker via `docker/exec`:

```bash
make install          # First-time setup
make start            # Start Docker containers
make stop             # Stop containers

make test             # Run all checks (phpcs, phpstan, phpmd, phpunit, security audit)
make test-phpcs       # Code style (PSR-12)
make fix-phpcs        # Auto-fix style violations
make test-phpstan     # Static analysis
make test-phpunit     # PHPUnit with coverage (95% threshold enforced)

# Run a single test file or method
docker/exec vendor/bin/phpunit tests/Cdn/CdnTest.php
docker/exec vendor/bin/phpunit --filter methodName tests/Cdn/CdnTest.php

make build            # Build production ZIP → build/latest.zip
make deploy           # Deploy to dev on AWS
make deploy-prod      # Deploy to production on AWS
```

## Architecture

**Entry point:** `public/index.php` → bootstraps `Container` → calls `Cdn::handleRequest()`

**Request flow:**
1. `Cdn` validates the request (GET only, domain in `ALLOWED_DOMAINS`)
2. `UriDecoder` parses the source URL and query parameters into a `QueryParams` DTO
3. `PathProcessor` generates a deterministic cache path from the params
4. If the cached file doesn't exist, `Storage` fetches the original via `UrlFilesystemAdapter` (Flysystem adapter wrapping Symfony's HTTP client)
5. For **images**: `ImageProcessor` applies transformations (Imagick): resize, compression, WebP conversion, watermark
6. For **static assets** (CSS/JS/fonts/SVG/ICO): `StaticAssetProcessor` minifies CSS and JS; other types are served as-is
7. Result is saved to storage (local filesystem or S3 via Flysystem)
8. `Cache` returns a Symfony `Response` with `Cache-Control`, `ETag`, and `Last-Modified` headers

**Key classes:**
- `src/Cdn.php` - orchestrates the full request lifecycle; routes to image or static asset processing
- `src/Container.php` - custom DI container; wires all services from env vars
- `src/Storage/Storage.php` - Flysystem abstraction (local or S3)
- `src/Processor/ImageProcessor.php` - ImageMagick transformations
- `src/Processor/StaticAssetProcessor.php` - CSS/JS minification (matthiasmullie/minify); font/SVG/ICO passthrough
- `src/Processor/PathProcessor.php` - cache key generation
- `src/Decoder/UriDecoder.php` - URL and query param parsing
- `src/Cache/Cache.php` - HTTP response and cache headers
- `src/Dto/QueryParams.php` - immutable DTO for image transformation parameters

**Exception-driven validation:** Domain/URI/extension/file errors are thrown as typed exceptions (`NotAllowedDomain`, `InvalidUri`, `FileNotFound`, etc.) and caught in `Cdn` to return appropriate HTTP responses.

## Testing

Tests mirror `src/` structure under `tests/`. The base `TestCase` class provides:
- In-memory Flysystem filesystem (no disk I/O)
- Mocked `UrlFilesystemAdapter` for remote image fetching
- `getContainer()` / `getQueryParameters()` / `getTestImageContent()` helpers

`ContainerConfig.php` and `UrlFilesystemAdapter.php` are excluded from coverage requirements.

## Configuration

The app is configured entirely via environment variables (see `.env`):

| Variable                                                                  | Purpose                                           |
|---------------------------------------------------------------------------|---------------------------------------------------|
| `ALLOWED_DOMAINS`                                                         | Comma-separated list of authorized source domains |
| `DOMAINS_ALIASES`                                                         | Domain aliases, e.g. `example.com/secret=ex`      |
| `STORAGE_DRIVER`                                                          | `local` or `s3`                                   |
| `S3_BUCKET`, `S3_ENDPOINT`, `S3_REGION`, `S3_ACCESS_KEY`, `S3_SECRET_KEY` | S3 config                                         |
| `IMAGE_COMPRESSION`                                                       | JPEG/WebP quality (0–100)                         |
| `CACHE_TTL`                                                               | HTTP cache TTL in seconds (default: 1 year)       |
| `LOG_LEVEL`                                                               | PSR-3 log level                                   |

In production, secrets are pulled from AWS SSM Parameter Store (see `serverless.yml`). Local dev uses MinIO (S3-compatible) on port 9001.

## Deployment

Deployed to AWS Lambda (PHP 8.4 FPM, Bref framework) with:
- 2048 MB memory, 28-second timeout
- Imagick Lambda layer
- Warm-up ping every 5 minutes via EventBridge
- GZIP compression via API Gateway `minimumCompressionSize: 1024` (responses > 1 KB compressed automatically)
- CI/CD via GitHub Actions (`.github/workflows/`)
