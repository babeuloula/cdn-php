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

namespace BaBeuloula\CdnPhp\Tests\Processor;

use BaBeuloula\CdnPhp\Decoder\UriDecoder;
use BaBeuloula\CdnPhp\Processor\PathProcessor;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PathProcessorTest extends TestCase
{
    #[DataProvider('queryParametersProvider')]
    #[Test]
    public function canGetPathWithoutWebp(string $path, array $queryParams): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . '?' . http_build_query($queryParams));
        $pathProcessor = new PathProcessor($decoder);

        $path = ('' === $path) ? '' : "/$path";

        static::assertSame(
            static::TEST_DOMAIN . $path . '/' . static::TEST_FILENAME_MD5,
            $pathProcessor->getPath()
        );
    }

    #[DataProvider('queryParametersProvider')]
    #[Test]
    public function canGetPathWithWebp(string $path, array $queryParams): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . '?' . http_build_query($queryParams));
        $pathProcessor = new PathProcessor($decoder);

        $path = ('' === $path) ? '' : "/$path";

        static::assertSame(
            static::TEST_DOMAIN . $path . '/' . static::TEST_FILENAME_MD5 . '.webp',
            $pathProcessor->getPath(false, true)
        );
    }

    public static function queryParametersProvider(): \Generator
    {
        yield ['w0', []];
        yield ['w100', ['w' => 100]];
        yield ['w0/h100', ['h' => 100]];
        yield [
            'w0/markexample-com-watermark-jpg/markposcenter/markw75w/markalpha50',
            ['wu' => static::TEST_WATERMARK_URL],
        ];
        yield ['w0', ['ws' => 50]];
        yield ['w0', ['wo' => 50]];

        yield ['w100/h100', ['w' => 100, 'h' => 100]];
    }

    #[DataProvider('queryParametersProvider')]
    #[Test]
    public function canGetPathWithAvif(string $path, array $queryParams): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI . '?' . http_build_query($queryParams));
        $pathProcessor = new PathProcessor($decoder);

        $path = ('' === $path) ? '' : "/$path";

        static::assertSame(
            static::TEST_DOMAIN . $path . '/' . static::TEST_FILENAME_MD5 . '.avif',
            $pathProcessor->getPath(true, false)
        );
    }

    #[Test]
    public function avifTakesPriorityOverWebpInPath(): void
    {
        $decoder = new UriDecoder(static::TEST_BASE_URI);
        $pathProcessor = new PathProcessor($decoder);

        static::assertSame(
            static::TEST_DOMAIN . '/w0/' . static::TEST_FILENAME_MD5 . '.avif',
            $pathProcessor->getPath(true, true)
        );
    }

    #[Test]
    public function canGetPathForTranscodedExtensions(): void
    {
        foreach (['avif', 'heic'] as $ext) {
            $imageUri = "https://example.com/image.{$ext}";
            $decoder = new UriDecoder($imageUri);
            $pathProcessor = new PathProcessor($decoder);

            // AVIF and HEIC use .jpg as base extension for browser-safe fallback
            $expectedFilename = md5($imageUri) . '.jpg';
            static::assertSame(
                static::TEST_DOMAIN . '/w0/' . $expectedFilename,
                $pathProcessor->getPath(),
                "Failed for extension: {$ext}",
            );
        }
    }

    #[Test]
    public function canGetPathWithDifferentExtensions(): void
    {
        foreach (['png', 'gif', 'webp'] as $ext) {
            $imageUri = "https://example.com/image.{$ext}";
            $decoder = new UriDecoder($imageUri);
            $pathProcessor = new PathProcessor($decoder);

            $expectedFilename = md5($imageUri) . ".{$ext}";
            static::assertSame(
                static::TEST_DOMAIN . '/w0/' . $expectedFilename,
                $pathProcessor->getPath(),
                "Failed for extension: {$ext}",
            );
        }
    }
}
