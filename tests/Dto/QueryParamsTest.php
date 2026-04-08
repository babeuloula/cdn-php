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

namespace BaBeuloula\CdnPhp\Tests\Dto;

use BaBeuloula\CdnPhp\Dto\QueryParams;
use BaBeuloula\CdnPhp\Enum\WatermarkPosition;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class QueryParamsTest extends TestCase
{
    #[Test]
    public function canGetWatermarkSize(): void
    {
        static::assertEquals(0, QueryParams::fromArray(['ws' => -1])->watermarkSize);
        static::assertEquals(100, QueryParams::fromArray(['ws' => 101])->watermarkSize);
        static::assertEquals(75, QueryParams::fromArray(['ws' => null])->watermarkSize);
        static::assertEquals(75, QueryParams::fromArray([])->watermarkSize);
    }

    #[Test]
    public function canNormalizeWatermarkOpacity(): void
    {
        static::assertEquals(0, QueryParams::fromArray(['wo' => -1])->watermarkOpacity);
        static::assertEquals(100, QueryParams::fromArray(['wo' => 101])->watermarkOpacity);
        static::assertEquals(50, QueryParams::fromArray(['wo' => null])->watermarkOpacity);
        static::assertEquals(50, QueryParams::fromArray([])->watermarkOpacity);
    }

    #[Test]
    public function canClampDimensions(): void
    {
        $params = QueryParams::fromArray(['w' => 99999, 'h' => 99999]);
        static::assertSame(QueryParams::MAX_DIMENSION, $params->width);
        static::assertSame(QueryParams::MAX_DIMENSION, $params->height);
    }

    #[Test]
    public function canFitMaxWhenWidthIsZeroWithHeight(): void
    {
        // fit='crop' requires BOTH width > 0 AND height set; width=0 must stay 'max'
        $array = QueryParams::fromArray(['w' => 0, 'h' => 100])->toArray();
        static::assertSame('max', $array['fit']);
    }

    #[Test]
    public function canFallbackToDefaultWatermarkPosition(): void
    {
        $params = QueryParams::fromArray(['wu' => 'example.com/wm.jpg', 'wp' => 'invalid-position']);
        static::assertSame(WatermarkPosition::default(), $params->watermarkPosition);
    }

    #[Test]
    public function canParseWatermarkUrlWithPort(): void
    {
        $params = QueryParams::fromArray(['wu' => 'http://example.com:8080/watermark.jpg']);
        static::assertSame('example.com:8080/watermark.jpg', $params->watermarkUrl);
    }

    #[Test]
    public function canConvertToAnArray(): void
    {
        static::assertEquals(
            [
                'w' => 300,
                'h' => 200,
                'mark' => 'example.com',
                'markpos' => WatermarkPosition::default()->value,
                'markw' => '50w',
                'markpad' => '3w',
                'markalpha' => 50,
                'fit' => 'crop',
            ],
            QueryParams::fromArray(
                [
                    'wu' => 'http://example.com',
                    'ws' => 50,
                    'h' => 200,
                    'wo' => 50,
                    'w' => 300,
                ]
            )->toArray()
        );

        static::assertEquals(
            [
                'w' => 300,
                'mark' => 'example.com',
                'markpos' => WatermarkPosition::default()->value,
                'markw' => '50w',
                'markpad' => '3w',
                'markalpha' => 50,
                'fit' => 'max',
            ],
            QueryParams::fromArray(
                [
                    'wu' => 'http://example.com',
                    'ws' => 50,
                    'wo' => 50,
                    'w' => 300,
                ]
            )->toArray()
        );
    }
}
