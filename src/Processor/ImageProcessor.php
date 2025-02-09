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
use BaBeuloula\CdnPhp\Flysystem\Adapter\UrlFilesystemAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemAdapter;
use League\Glide\ServerFactory;
use Psr\Log\LoggerInterface;

final class ImageProcessor
{
    public function __construct(
        private readonly FilesystemAdapter $adapter,
        private readonly UrlFilesystemAdapter $urlFilesystemAdapter,
        private readonly LoggerInterface $logger,
        private readonly int $imageCompression,
    ) {
    }

    public function process(string $path, QueryParams $params): string
    {
        $server = ServerFactory::create(
            [
                'source' => new Filesystem($this->adapter),
                'source_path_prefix' => \dirname($path),
                'cache' => new Filesystem($this->adapter),
                'cache_path_prefix' => \dirname(\dirname($path)) . '/cache',
                'watermarks' => new Filesystem($this->urlFilesystemAdapter),
                'driver' => 'imagick',
            ],
        );

        $this->logger->info(
            'Process image: {path} with params {params}',
            [
                'path' => $path,
                'params' => json_encode($params->toArray(), flags: JSON_THROW_ON_ERROR),
            ]
        );

        return $server->makeImage(basename($path), [...$params->toArray(), 'q' => $this->imageCompression]);
    }
}
