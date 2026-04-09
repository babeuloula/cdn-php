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

namespace BaBeuloula\CdnPhp\Processor;

use BaBeuloula\CdnPhp\Decoder\UriDecoder;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class PathProcessor
{
    /**
     * Formats that cannot be served directly by browsers and must be transcoded.
     * The value is the web-safe fallback extension used as the base cache path.
     *
     * @var array<string, string>
     */
    private const array TRANSCODED_EXTENSIONS = [
        'avif' => 'jpg',
        'heic' => 'jpg',
    ];

    private string $path;

    public function __construct(private readonly UriDecoder $decoder)
    {
        $this->generatePath();
    }

    public function getPath(bool $supportAvif = false, bool $supportWebp = false): string
    {
        if (true === $supportAvif) {
            return $this->path . '.avif';
        }

        if (true === $supportWebp) {
            return $this->path . '.webp';
        }

        return $this->path;
    }

    private function generatePath(): void
    {
        $params = $this->decoder->getParams()->toArray();
        unset($params['fit'], $params['markpad']);

        if (true === \is_string($this->decoder->getParams()->watermarkUrl)) {
            $params['mark'] = (new AsciiSlugger())->slug($this->decoder->getParams()->watermarkUrl)->toString();
        }

        ksort($params);

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = "{$key}{$value}";
        }
        $path = implode('/', $parts);

        $sourceExtension = strtolower(pathinfo($this->decoder->getImageUrl(), PATHINFO_EXTENSION));
        $extension = self::TRANSCODED_EXTENSIONS[$sourceExtension] ?? $sourceExtension;
        $filename = md5($this->decoder->getImageUrl()) . '.' . $extension;

        $this->path = sprintf(
            '%s%s/%s',
            $this->decoder->getDomain(),
            ('' === $path) ? '' : "/$path",
            $filename,
        );
    }
}
