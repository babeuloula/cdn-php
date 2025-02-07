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

use BaBeuloula\CdnPhp\Dto\QueryParams;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Glide\ServerFactory;

final class ImageProcessor
{
    public function __construct(private readonly FilesystemAdapter $adapter)
    {
    }

    public function process(string $path, QueryParams $params): string
    {
        $server = ServerFactory::create(
            [
                'source' => new Filesystem($this->adapter),
                'source_path_prefix' => \dirname($path),
                'cache' => new Filesystem($this->adapter),
                'cache_path_prefix' => \dirname(\dirname($path)) . '/cache',
                'driver' => 'imagick',
            ],
        );

        return $server->makeImage(basename($path), $params->toArray());
    }
}
