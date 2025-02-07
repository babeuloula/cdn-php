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
}
