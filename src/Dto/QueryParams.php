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

namespace BaBeuloula\CdnPhp\Dto;

use BaBeuloula\CdnPhp\Enum\WatermarkPosition;

final class QueryParams
{
    public const string PARAM_WIDTH = 'w';
    public const string PARAM_HEIGHT = 'h';
    public const string PARAM_WATERMARK_URL = 'wu';
    public const string PARAM_WATERMARK_POSITION = 'wp';
    public const string PARAM_WATERMARK_SIZE = 'ws';
    public const string PARAM_WATERMARK_OPACITY = 'wo';
    public const int MAX_DIMENSION = 5000;

    public readonly ?string $watermarkUrl;
    public readonly int $watermarkSize;
    public readonly int $watermarkOpacity;

    private function __construct(
        public readonly int $width,
        public readonly ?int $height,
        ?string $watermarkUrl,
        public readonly WatermarkPosition $watermarkPosition,
        int $watermarkSize,
        int $watermarkOpacity,
    ) {
        $this->watermarkUrl = (true === str_contains((string) $watermarkUrl, '://'))
            ? (explode('://', (string) $watermarkUrl)[1] ?? null)
            : $watermarkUrl
        ;

        if ($watermarkSize < 0) {
            $watermarkSize = 0;
        }

        if ($watermarkSize > 100) {
            $watermarkSize = 100;
        }

        if ($watermarkOpacity < 0) {
            $watermarkOpacity = 0;
        }

        if ($watermarkOpacity > 100) {
            $watermarkOpacity = 100;
        }

        $this->watermarkSize = $watermarkSize;
        $this->watermarkOpacity = $watermarkOpacity;
    }

    /** @param array<string, int|string> $query */
    public static function fromArray(array $query): QueryParams
    {
        // phpcs:disable
        return new self(
            empty($query[self::PARAM_WIDTH]) ? 0 : min((int) $query[self::PARAM_WIDTH], self::MAX_DIMENSION),
            empty($query[self::PARAM_HEIGHT]) ? null : min((int) $query[self::PARAM_HEIGHT], self::MAX_DIMENSION),
            empty($query[self::PARAM_WATERMARK_URL]) ? null : ((string) $query[self::PARAM_WATERMARK_URL]),
            empty($query[self::PARAM_WATERMARK_POSITION]) ? WatermarkPosition::default() : (WatermarkPosition::tryFrom($query[self::PARAM_WATERMARK_POSITION]) ?? WatermarkPosition::default()),
            empty($query[self::PARAM_WATERMARK_SIZE]) ? 75 : ((int) $query[self::PARAM_WATERMARK_SIZE]),
            empty($query[self::PARAM_WATERMARK_OPACITY]) ? 50 : ((int) $query[self::PARAM_WATERMARK_OPACITY]),
        );
        // phpcs:enable
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        $params = [
            self::PARAM_WIDTH => $this->width,
            self::PARAM_HEIGHT => $this->height,
            'fit' => 'max',
        ];

        if (true === \is_string($this->watermarkUrl)) {
            $params = array_merge(
                $params,
                [
                    'mark' => $this->watermarkUrl,
                    'markpos' => $this->watermarkPosition->value,
                    'markw' => $this->watermarkSize . 'w',
                    'markpad' => '3w',
                    'markalpha' => $this->watermarkOpacity,
                ],
            );
        }

        if ($this->width > 0 && true === \is_int($this->height)) {
            $params['fit'] = 'crop';
        }

        return array_filter($params, static fn (mixed $value) => null !== $value);
    }
}
