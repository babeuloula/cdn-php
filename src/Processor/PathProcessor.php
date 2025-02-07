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
    private string $path;

    public function __construct(private readonly UriDecoder $decoder)
    {
        $this->generatePath();
    }

    public function getPath(bool $supportWebp = false): string
    {
        return $this->path . ((true === $supportWebp) ? '.webp' : '');
    }

    /**
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    private function arrayMapAssoc(callable $callback, array $array): array
    {
        return array_map(
            static function (mixed $key) use ($callback, $array) {
                return $callback($key, $array[$key]);
            },
            array_keys($array),
        );
    }

    private function generatePath(): void
    {
        $params = $this->decoder->getParams()->toArray();
        unset($params['fit']);

        if (true === \is_string($this->decoder->getParams()->watermarkUrl)) {
            $params['mark'] = (new AsciiSlugger())->slug($this->decoder->getParams()->watermarkUrl)->toString();
        }

        $path = $this->arrayMapAssoc(static fn ($k, $v) => "$k$v", $params);

        $extension = pathinfo($this->decoder->getImageUrl(), PATHINFO_EXTENSION);
        $filename = md5($this->decoder->getImageUrl()) . '.' . $extension;

        $this->path = sprintf(
            '%s/%s/%s',
            $this->decoder->getDomain(),
            implode('/', $path),
            $filename,
        );
    }
}
