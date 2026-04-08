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

namespace BaBeuloula\CdnPhp\Storage;

use BaBeuloula\CdnPhp\Exception\FileNotFoundException;
use BaBeuloula\CdnPhp\Exception\FileTooLargeException;
use BaBeuloula\CdnPhp\Http\HttpFetcher;
use League\Flysystem\Filesystem;
use Psr\Log\LoggerInterface;

final class Storage
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly HttpFetcher $httpFetcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetchImage(string $imageUrl, string $domain, bool $force = false): string
    {
        $this->logger->debug('Fetching image: {imageUrl}', ['imageUrl' => $imageUrl]);

        $extension = pathinfo($imageUrl, PATHINFO_EXTENSION);
        $filename = md5($imageUrl) . '.' . $extension;
        $path = sprintf(
            '%s/original/%s',
            $domain,
            $filename,
        );

        if (true === $this->exists($path) && false === $force) {
            $this->logger->debug('Original image already saved: {path}', ['path' => $path]);

            return $path;
        }

        try {
            $content = $this->httpFetcher->fetch($imageUrl);
        } catch (FileTooLargeException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw new FileNotFoundException($imageUrl, $e);
        }

        $this->save($path, $content);

        return $path;
    }

    public function read(string $path): string
    {
        return $this->filesystem->read($path);
    }

    /** @return resource */
    public function readStream(string $path)
    {
        return $this->filesystem->readStream($path);
    }

    public function mimeType(string $path): string
    {
        return $this->filesystem->mimeType($path);
    }

    public function fileSize(string $path): int
    {
        return $this->filesystem->fileSize($path);
    }

    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified($path);
    }

    public function save(string $path, string $content): void
    {
        $this->logger->debug('Save image on storage: {path}', ['path' => $path]);

        $this->filesystem->write($path, $content);
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }
}
