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
use League\Flysystem\Config;
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

    public function process(
        string $path,
        QueryParams $params,
        bool $outputAvif = false,
        bool $outputWebp = false,
    ): string {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ('gif' === $extension) {
            return $this->processAnimated($path, $params, $outputWebp);
        }

        // Animated WebP sources always stay WebP regardless of the Accept header
        if ('webp' === $extension) {
            return $this->processAnimated($path, $params, true);
        }

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

        $glideParams = [...$params->toArray(), 'q' => $this->imageCompression];

        if (true === $outputAvif) {
            $glideParams['fm'] = 'avif';
        } elseif (true === $outputWebp) {
            $glideParams['fm'] = 'webp';
        } elseif (true === \in_array($extension, ['avif', 'heic'], true)) {
            // Non-web-safe formats: always transcode to JPEG as browser-safe fallback
            $glideParams['fm'] = 'jpg';
        }

        $this->logger->info(
            'Process image: {path} with params {params}',
            [
                'path' => $path,
                'params' => json_encode($glideParams, flags: JSON_THROW_ON_ERROR),
            ]
        );

        $cachePath = $server->makeImage(basename($path), $glideParams);
        $this->stripExif($cachePath);

        return $cachePath;
    }

    public function extractDominantColor(string $path): ?string
    {
        try {
            $imagick = new \Imagick();
            $imagick->readImageBlob($this->adapter->read($path));
            $imagick->resizeImage(1, 1, \Imagick::FILTER_LANCZOS, 1);
            $pixel = $imagick->getImagePixelColor(0, 0);
            $color = $pixel->getColor();
            $imagick->clear();

            return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stripExif(string $path): void
    {
        try {
            $content = $this->adapter->read($path);
            $imagick = new \Imagick();
            $imagick->readImageBlob($content);
            $imagick->stripImage();
            $this->adapter->write($path, $imagick->getImagesBlob(), new Config());
            $imagick->clear();
        } catch (\Throwable) {
            // Non-blocking: EXIF stripping failure must not prevent serving the image
        }
    }

    private function processAnimated(string $path, QueryParams $params, bool $outputWebp = false): string
    {
        $content = $this->adapter->read($path);

        $imagick = new \Imagick();
        $imagick->readImageBlob($content);
        $animation = $imagick->coalesceImages();

        if ($params->width > 0 || null !== $params->height) {
            $width = $params->width > 0 ? $params->width : 0;
            $height = $params->height ?? 0;
            // bestfit only makes sense when both dimensions are constrained
            $bestfit = false;
            if ($width > 0 && $height > 0) {
                $bestfit = true;
            }

            foreach ($animation as $frame) {
                $frame->thumbnailImage($width, $height, $bestfit);
            }

            $animation = $animation->deconstructImages();
        }

        if (true === $outputWebp) {
            $animation->setFormat('WEBP');
        }

        $animation->stripImage();
        $blob = $animation->getImagesBlob();
        $animation->clear();
        $imagick->clear();

        $outputExtension = true === $outputWebp ? 'webp' : 'gif';
        $basename = pathinfo($path, PATHINFO_FILENAME) . '.' . $outputExtension;
        $cachePath = \dirname(\dirname($path)) . '/cache/' . $basename;
        $this->adapter->write($cachePath, $blob, new Config());

        $this->logger->info(
            'Process animated image: {path} with params {params}',
            [
                'path' => $path,
                'params' => json_encode($params->toArray(), flags: JSON_THROW_ON_ERROR),
            ]
        );

        return $cachePath;
    }
}
