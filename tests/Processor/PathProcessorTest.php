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
        yield ['h100/w0', ['h' => 100]];
        yield [
            'markexample-com-watermark-jpg/markalpha50/markposcenter/markw75w/w0',
            ['wu' => static::TEST_WATERMARK_URL],
        ];
        yield ['w0', ['ws' => 50]];
        yield ['w0', ['wo' => 50]];

        yield ['h100/w100', ['w' => 100, 'h' => 100]];
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

    #[Test]
    public function staticAssetPathIsUnderDomainStaticFolder(): void
    {
        $decoder = new UriDecoder(static::TEST_CSS_URI);
        $pathProcessor = new PathProcessor($decoder, false);

        static::assertSame(
            static::TEST_DOMAIN . '/static/' . static::TEST_CSS_FILENAME_MD5,
            $pathProcessor->getPath(),
        );
    }

    #[Test]
    public function staticAssetPathIgnoresResizeQueryParams(): void
    {
        $decoder = new UriDecoder(static::TEST_CSS_URI . '?w=300&h=200');
        $pathProcessor = new PathProcessor($decoder, false);

        static::assertSame(
            static::TEST_DOMAIN . '/static/' . static::TEST_CSS_FILENAME_MD5,
            $pathProcessor->getPath(),
        );
    }

    #[DataProvider('staticExtensionsProvider')]
    #[Test]
    public function staticAssetPathPreservesExtension(string $uri, string $expectedFilename): void
    {
        $decoder = new UriDecoder($uri);
        $pathProcessor = new PathProcessor($decoder, false);

        static::assertSame(
            static::TEST_DOMAIN . '/static/' . $expectedFilename,
            $pathProcessor->getPath(),
        );
    }

    public static function staticExtensionsProvider(): \Generator
    {
        yield [static::TEST_CSS_URI, static::TEST_CSS_FILENAME_MD5];
        yield [static::TEST_JS_URI, static::TEST_JS_FILENAME_MD5];
        yield [static::TEST_WOFF2_URI, static::TEST_WOFF2_FILENAME_MD5];
    }

    #[Test]
    public function imagePathWithVersionProducesDifferentHash(): void
    {
        $decoderWithVersion = new UriDecoder(static::TEST_BASE_URI . '?v=2');
        $pathWithVersion = (new PathProcessor($decoderWithVersion))->getPath();

        $decoderWithout = new UriDecoder(static::TEST_BASE_URI);
        $pathWithout = (new PathProcessor($decoderWithout))->getPath();

        static::assertNotSame($pathWithVersion, $pathWithout);
        static::assertStringStartsWith(static::TEST_DOMAIN . '/w0/', $pathWithVersion);
        static::assertStringEndsWith('1de6bb65981e48d8780955906993fe95.jpg', $pathWithVersion);
    }

    #[Test]
    public function staticAssetWithVersionProducesDifferentHash(): void
    {
        $decoderWithVersion = new UriDecoder(static::TEST_CSS_URI . '?v=2');
        $pathWithVersion = (new PathProcessor($decoderWithVersion, false))->getPath();

        $decoderWithout = new UriDecoder(static::TEST_CSS_URI);
        $pathWithout = (new PathProcessor($decoderWithout, false))->getPath();

        static::assertNotSame($pathWithVersion, $pathWithout);
        static::assertSame(
            static::TEST_DOMAIN . '/static/f9f2413e520d0357110782f27b274a73.css',
            $pathWithVersion,
        );
    }

    #[Test]
    public function staticAssetWithVersionIgnoresResizeParams(): void
    {
        $decoderWithBoth = new UriDecoder(static::TEST_CSS_URI . '?w=300&v=2');
        $path = (new PathProcessor($decoderWithBoth, false))->getPath();

        static::assertSame(
            static::TEST_DOMAIN . '/static/f9f2413e520d0357110782f27b274a73.css',
            $path,
        );
    }
}
