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

use BaBeuloula\CdnPhp\Decoder\UriDecoder;
use BaBeuloula\CdnPhp\Exception\FileNotFoundException;
use League\Flysystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class Storage
{
    private UriDecoder $decoder;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly SymfonyFilesystem $symfonyFilesystem,
    ) {
    }

    public function setDecoder(UriDecoder $decoder): self
    {
        $this->decoder = $decoder;

        return $this;
    }

    public function fetchImage(string $imageUrl): string
    {
        $extension = pathinfo($imageUrl, PATHINFO_EXTENSION);
        $filename = md5($imageUrl) . '.' . $extension;
        $path = sprintf(
            '%s/original/%s',
            $this->decoder->getDomain(),
            $filename,
        );

        if (true === $this->exists($path)) {
            return $path;
        }

        try {
            $content = $this->symfonyFilesystem->readFile($imageUrl);
        } catch (IOException $e) {
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
        $this->filesystem->write($path, $content);
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists($path);
    }
}
