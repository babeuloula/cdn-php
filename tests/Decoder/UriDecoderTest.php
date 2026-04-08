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

namespace BaBeuloula\CdnPhp\Tests\Decoder;

use BaBeuloula\CdnPhp\Decoder\UriDecoder;
use BaBeuloula\CdnPhp\Dto\QueryParams;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UriDecoderTest extends TestCase
{
    #[Test]
    public function canGetUri(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . $this->getQueryParameters());

        static::assertSame(static::TEST_BASE_URI . $this->getQueryParameters(), $decoder->getUri());
    }

    #[Test]
    public function canGetUriWithAnAlias(): void
    {
        $decoder = new UriDecoder(
            static::TEST_BASE_URI_ALIAS . $this->getQueryParameters(),
            [static::TEST_DOMAIN_ALIAS => static::TEST_DOMAIN],
        );

        static::assertSame(static::TEST_BASE_URI . $this->getQueryParameters(), $decoder->getUri());
    }

    #[Test]
    public function canGetImageUrl(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . $this->getQueryParameters());

        static::assertSame(static::TEST_BASE_URI, $decoder->getImageUrl());
    }

    #[Test]
    public function canGetDomain(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . $this->getQueryParameters());

        static::assertSame(static::TEST_DOMAIN, $decoder->getDomain());
    }

    #[Test]
    public function canGetFilename(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . $this->getQueryParameters());

        static::assertSame(static::TEST_FILENAME, $decoder->getFilename());
    }

    #[Test]
    public function canGetExtension(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . $this->getQueryParameters());

        static::assertSame(static::TEST_EXTENSION, $decoder->getExtension());
    }

    #[Test]
    public function canGetQueryParams(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . $this->getQueryParameters());

        static::assertInstanceOf(QueryParams::class, $decoder->getParams());
    }

    #[Test]
    public function canHandleUnknownAlias(): void
    {
        // An alias not present in the map results in an invalid URL (domain replaced by empty string)
        $decoder = new UriDecoder(
            '_nonexistent_/example.com/image.jpg',
            ['other_alias' => 'example.com'],
        );

        static::assertStringStartsWith('https://', $decoder->getUri());
        static::assertStringNotContainsString('_nonexistent_', $decoder->getUri());
    }

    #[Test]
    public function canHandleNonHttpScheme(): void
    {
        // ftp:// is not stripped, so it ends up wrapped in https:// and will fail URL validation
        $decoder = new UriDecoder('ftp://example.com/image.jpg');

        static::assertSame('https://ftp://example.com/image.jpg', $decoder->getUri());
    }
}
