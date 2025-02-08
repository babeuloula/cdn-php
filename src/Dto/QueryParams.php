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
    public readonly ?string $watermarkUrl;
    public readonly int $watermarkSize;
    public readonly int $watermarkOpacity;

    private function __construct(
        public readonly ?int $width,
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
            empty($query['w']) ? null : ((int) $query['w']),
            empty($query['h']) ? null : ((int) $query['h']),
            empty($query['wu']) ? null : ((string) $query['wu']),
            empty($query['wp']) ? WatermarkPosition::default() : (WatermarkPosition::tryFrom($query['wp']) ?? WatermarkPosition::default()),
            empty($query['ws']) ? 75 : ((int) $query['ws']),
            empty($query['wo']) ? 50 : ((int) $query['wo']),
        );
        // phpcs:enable
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        $params = [
            'w' => $this->width,
            'h' => $this->height,
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

        if (true === \is_int($this->width) && true === \is_int($this->height)) {
            $params['fit'] = 'crop';
        }

        return array_filter($params);
    }
}
