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
    public function canConvertToAnArray(): void
    {
        static::assertEquals(
            [
                'w' => 300,
                'h' => 200,
                'wu' => 'foo-url',
                'ws' => 50,
                'wo' => 50,
                'fit' => 'crop',
            ],
            QueryParams::fromArray(
                [
                    'wu' => 'foo-url',
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
                'wu' => 'foo-url',
                'ws' => 50,
                'wo' => 50,
                'fit' => 'max',
            ],
            QueryParams::fromArray(
                [
                    'wu' => 'foo-url',
                    'ws' => 50,
                    'wo' => 50,
                    'w' => 300,
                ]
            )->toArray()
        );
    }
}
