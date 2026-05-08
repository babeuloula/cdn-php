# Adding a Caching Layer

This document explains how to add a caching layer in front of your CDN Lambda to avoid invoking it on every request. Once an image has been resized and cached, subsequent requests are served directly from the cache without triggering the Lambda.

## How it works

```
User → Cache Layer → API Gateway → Lambda (resize) → S3
              ↓
        Cache HIT → Serve directly ✅ (Lambda not invoked)
        Cache MISS → Forward to Lambda → Cache the response
```

The cache key is based on the full URL including query parameters (e.g. `?w=300&h=200`), so each unique combination of image and dimensions is cached separately.

## Prerequisites

Your Lambda **must** return a `Cache-Control` header in every response, otherwise the cache layer will not store the response:

```
Cache-Control: public, max-age=2592000
```

Adjust `max-age` to your desired TTL in seconds (e.g. `2592000` = 30 days, `31536000` = 1 year).

---

## Cloudflare (free)

Cloudflare is the simplest and most cost-effective solution. It acts as a reverse proxy in front of your API Gateway and caches responses at the edge.

### Requirements

- Your domain must be managed by Cloudflare (nameservers pointed to Cloudflare)
- Your DNS record for your CDN subdomain must be **Proxied** (orange cloud) in Cloudflare

### Step 1 — Add your domain to Cloudflare

1. Create a free account at [cloudflare.com](https://cloudflare.com)
2. Click **Add a site** → **Connect a domain** → enter your domain
3. Cloudflare will scan and import your existing DNS records
4. Verify all records are correctly imported
5. Update your nameservers at your registrar to the ones provided by Cloudflare
6. Wait for propagation (usually 1–2 hours)

### Step 2 — Configure your DNS record

In **Cloudflare → DNS**, make sure your CDN subdomain CNAME record is set to **Proxied** (orange cloud icon):

```
cdn.yourdomain.com  CNAME  your-api-gateway-custom-domain.execute-api.region.amazonaws.com  [Proxied 🟠]
```

> ⚠️ Do **not** configure a custom domain in API Gateway for this subdomain. Cloudflare handles the domain — API Gateway should only be reachable via its native URL.

### Step 3 — Create a Cache Rule

Go to **Cloudflare → Caching → Cache Rules → Create rule**:

| Field                        | Value                                                                |
|------------------------------|----------------------------------------------------------------------|
| Rule name                    | `Cache CDN images`                                                   |
| When incoming requests match | `URI Full` `wildcard` `https://cdn.yourdomain.com/*`                 |
| Cache eligibility            | `Eligible for cache`                                                 |
| Edge TTL                     | `Ignore cache-control header and use this TTL` → `2592000` (30 days) |

Click **Deploy**.

### Step 4 — Verify

```bash
# First request — Cache MISS (Lambda is invoked)
curl -I "https://cdn.yourdomain.com/path/to/image.jpg?w=700&h=400"
# cf-cache-status: MISS

# Second request — Cache HIT (Lambda is NOT invoked) ✅
curl -I "https://cdn.yourdomain.com/path/to/image.jpg?w=700&h=400"
# cf-cache-status: HIT
```

### Bypass cache for a specific request

To bypass the cache for a specific request (e.g. to force a fresh resize), add a `version` query parameter with any value:

```
https://cdn.yourdomain.com/path/to/image.jpg?w=700&h=400&version=abc123
```

Since `version` changes the cache key, Cloudflare will treat it as a new uncached request.

### Purge the cache

To manually purge cached images, go to **Cloudflare → Caching → Cache Purge**:

- **Purge everything** — clears the entire cache
- **Custom purge** — enter specific URLs to purge (supports wildcards on paid plans)
