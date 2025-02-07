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

namespace BaBeuloula\CdnPhp\Tests\Cache;

use BaBeuloula\CdnPhp\Cache\Cache;
use BaBeuloula\CdnPhp\Tests\TestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $adapter->write(
            static::TEST_FILENAME,
            static::getTestImageContent(),
            new Config(),
        );
    }

    #[Test]
    public function canCreateAResponseWithoutWebpSupport(): void
    {
        $defaultTtl = $this->getContainer('cache_ttl');

        /** @var Cache $cache */
        $cache = $this->getContainer(Cache::class);

        $response = $cache->createResponse(static::TEST_FILENAME, false, new Request());

        static::assertInstanceOf(StreamedResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('image/jpeg', $response->headers->get('Content-Type'));
        static::assertGreaterThan(0, $response->headers->get('Content-Length'));
        static::assertSame('max-age=' . $defaultTtl . ', public', $response->headers->get('Cache-Control'));
        static::assertNotNull($response->headers->get('Last-Modified'));
    }

    #[Test]
    public function canCreateAResponseWithWebpSupport(): void
    {
        /** @var Cache $cache */
        $cache = $this->getContainer(Cache::class);

        $response = $cache->createResponse(static::TEST_FILENAME, true, new Request());

        static::assertSame('image/webp', $response->headers->get('Content-Type'));
    }
}
