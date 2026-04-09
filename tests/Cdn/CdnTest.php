<?php

/**
 * @author      BaBeuloula <info@babeuloula.fr>
 * @copyright   Copyright (c) BaBeuloula
 * @license     MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace BaBeuloula\CdnPhp\Tests\Cdn;

use BaBeuloula\CdnPhp\Cache\Cache;
use BaBeuloula\CdnPhp\Cdn;
use BaBeuloula\CdnPhp\Processor\ImageProcessor;
use BaBeuloula\CdnPhp\Processor\StaticAssetProcessor;
use BaBeuloula\CdnPhp\Security\UrlSigner;
use BaBeuloula\CdnPhp\Storage\Storage;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CdnTest extends TestCase
{
    private Cdn $cdn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cdn = $this->getContainer(Cdn::class);
    }

    #[DataProvider('methodNotAllowedProvider')]
    #[Test]
    public function cantHandleRequest(string $method): void
    {
        $request = Request::create(static::TEST_BASE_URI, $method);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }

    public static function methodNotAllowedProvider(): \Generator
    {
        yield [Request::METHOD_HEAD];
        yield [Request::METHOD_POST];
        yield [Request::METHOD_PUT];
        yield [Request::METHOD_PATCH];
        yield [Request::METHOD_DELETE];
        yield [Request::METHOD_PURGE];
        yield [Request::METHOD_OPTIONS];
        yield [Request::METHOD_TRACE];
        yield [Request::METHOD_CONNECT];
    }

    #[Test]
    public function canHandleRequestWithEmptyUri(): void
    {
        $request = Request::create('');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequestWithEmptyUriAndVersion(): void
    {
        $cdn = new Cdn(
            $this->getContainer('allowed_domains'),
            $this->getContainer('domains_aliases'),
            $this->getContainer(Storage::class),
            $this->getContainer(ImageProcessor::class),
            $this->getContainer(StaticAssetProcessor::class),
            $this->getContainer(Cache::class),
            $this->getContainer(LoggerInterface::class),
            '',
            null,
            '1.2.3'
        );
        $request = Request::create('');

        $response = $cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString('(v1.2.3)', $response->getContent());
    }

    #[Test]
    public function cantHandleRequestInvalidUri(): void
    {
        $request = Request::create('this_is_not_a_valid_uri');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestNotSupportedExtension(): void
    {
        $request = Request::create('http://mycdn.com/http://example.com/page.php');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestNotAllowedDomain(): void
    {
        $request = Request::create('http://mycdn.com/http://not-allowed.com/image.jpg');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithANotFoundImage(): void
    {
        $request = Request::create('http://mycdn.com/http://' . static::TEST_DOMAIN . '/not-found.jpg');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequest(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequestWithAllowedWatermarkDomain(): void
    {
        // Watermark from an allowed domain must not be blocked
        $params = http_build_query(['wu' => static::TEST_WATERMARK_URL]);
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequestWithUppercaseExtension(): void
    {
        // Extension check must be case-insensitive (.JPG treated like .jpg)
        $request = Request::create('http://mycdn.com/https://example.com/image.JPG');

        $response = $this->cdn->handleRequest($request);

        // 404 (image not mocked), not 400 (unsupported extension)
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithNonHttpScheme(): void
    {
        // ftp:// is not stripped by UriDecoder, so the parsed domain becomes "ftp" → not in allowedDomains
        $request = Request::create('http://mycdn.com/ftp://example.com/image.jpg');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithNotAllowedWatermarkDomain(): void
    {
        $params = http_build_query(['wu' => 'https://not-allowed.com/watermark.jpg']);
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequestAndForceReFetch(): void
    {
        $params = http_build_query(['force' => true, 'token' => static::TEST_FORCE_TOKEN]);
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params);

        $response = $this->cdn->handleRequest($request);

        static::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function cantForceReFetchWithWrongToken(): void
    {
        // Pre-populate cache
        $this->cdn->handleRequest(Request::create('http://mycdn.com/' . static::TEST_BASE_URI));

        // Force with wrong token is silently ignored
        $params = http_build_query(['force' => true, 'token' => 'wrong-token']);
        $response = $this->cdn->handleRequest(
            Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithTooLargeImage(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_TOO_LARGE_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithCorruptImage(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_CORRUPT_IMAGE_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function canHandleGifRequest(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_GIF_BASE_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('image/gif', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function canHandleGifRequestWithWebpAccept(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_GIF_BASE_URI);
        $request->headers->set('Accept', 'image/webp,*/*');

        $response = $this->cdn->handleRequest($request);

        // Animated GIFs are converted to animated WebP when the client supports it
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('image/webp', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function canHandleAnimatedWebpRequest(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_ANIMATED_WEBP_BASE_URI);

        $response = $this->cdn->handleRequest($request);

        // Animated WebP sources must preserve animation and stay as WebP
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('image/webp', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function canHandleRequestWithCssAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_CSS_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('text/css', $response->headers->get('Content-Type'));
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithJsAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_JS_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString('javascript', (string) $response->headers->get('Content-Type'));
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithFontAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_WOFF2_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithXmlAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_XML_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function staticAssetsIgnoreWebpAcceptHeader(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_CSS_URI);
        $request->headers->set('Accept', 'image/webp,*/*');

        $response = $this->cdn->handleRequest($request);

        // CSS assets must never be served as WebP regardless of Accept header
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('text/css', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function canHandleRequestWithAvifOutput(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI);
        $request->headers->set('Accept', 'image/avif,*/*');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('image/avif', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function avifOutputTakesPriorityOverWebp(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI);
        $request->headers->set('Accept', 'image/avif,image/webp,*/*');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('image/avif', $response->headers->get('Content-Type'));
    }

    #[Test]
    public function canRecognizeHeicExtensionAsValidImageFormat(): void
    {
        // HEIC is a valid image extension - must not be rejected with 400 (unsupported extension)
        $request = Request::create('http://mycdn.com/' . static::TEST_HEIC_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequestWithJsonAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_JSON_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithWebmanifestAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_WEBMANIFEST_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithTxtAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_TXT_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithMapAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_MAP_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithWasmAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_WASM_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertNull($response->headers->get('Vary'));
    }

    #[Test]
    public function canHandleRequestWithDominantColorHeader(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $color = $response->headers->get('X-Dominant-Color');
        static::assertNotNull($color);
        static::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', (string) $color);
    }

    #[Test]
    public function cantHandleRequestWithCorruptJsonAsset(): void
    {
        $request = Request::create('http://mycdn.com/' . static::TEST_CORRUPT_JSON_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    #[Test]
    public function canHandleRequestWithSignedUrl(): void
    {
        $expires = time() + 3600;
        $sig = hash_hmac('sha256', static::TEST_BASE_URI . ':' . $expires, static::TEST_FORCE_TOKEN);
        $params = http_build_query(['expires' => $expires, 'sig' => $sig]);

        $cdn = $this->getCdnWithSignatureSecret(static::TEST_FORCE_TOKEN);
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params);

        $response = $cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithInvalidSignature(): void
    {
        $expires = time() + 3600;
        $params = http_build_query(['expires' => $expires, 'sig' => 'wrong-signature']);

        $cdn = $this->getCdnWithSignatureSecret(static::TEST_FORCE_TOKEN);
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params);

        $response = $cdn->handleRequest($request);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestWithExpiredSignedUrl(): void
    {
        $expires = time() - 1;
        $sig = hash_hmac('sha256', static::TEST_BASE_URI . ':' . $expires, static::TEST_FORCE_TOKEN);
        $params = http_build_query(['expires' => $expires, 'sig' => $sig]);

        $cdn = $this->getCdnWithSignatureSecret(static::TEST_FORCE_TOKEN);
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI . '?' . $params);

        $response = $cdn->handleRequest($request);

        static::assertSame(Response::HTTP_GONE, $response->getStatusCode());
    }

    #[Test]
    public function skipsSignatureCheckWhenSecretIsEmpty(): void
    {
        // Default CDN has no signature secret - any request must pass without expires/sig
        $request = Request::create('http://mycdn.com/' . static::TEST_BASE_URI);

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function getCdnWithSignatureSecret(string $secret): Cdn
    {
        return new Cdn(
            $this->getContainer('allowed_domains'),
            $this->getContainer('domains_aliases'),
            $this->getContainer(Storage::class),
            $this->getContainer(ImageProcessor::class),
            $this->getContainer(StaticAssetProcessor::class),
            $this->getContainer(Cache::class),
            $this->getContainer(LoggerInterface::class),
            '',
            new UrlSigner($secret),
        );
    }
}
