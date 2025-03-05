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

use BaBeuloula\CdnPhp\Cdn;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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
    public function cantHandleRequestInvalidUri(): void
    {
        $request = Request::create('this_is_not_a_valid_uri');

        $response = $this->cdn->handleRequest($request);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function cantHandleRequestNotSupportedExtension(): void
    {
        $request = Request::create('http://mycdn.com/http://example.com/style.css');

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
    public function canHandleRequestAndForceReFetch(): void
    {
        $request = Request::create(
            'http://mycdn.com/' . static::TEST_BASE_URI . '?' . http_build_query(['force' => true])
        );

        $response = $this->cdn->handleRequest($request);

        static::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
