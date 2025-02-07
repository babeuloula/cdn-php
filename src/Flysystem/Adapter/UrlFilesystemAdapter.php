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

namespace BaBeuloula\CdnPhp\Flysystem\Adapter;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class UrlFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(private readonly SymfonyFilesystem $symfonyFilesystem)
    {
    }

    public function fileExists(string $path): bool
    {
        $path = 'https://' . $path;

        return str_contains(get_headers($path)[0] ?? '', '200 OK');
    }

    public function directoryExists(string $path): bool
    {
        throw new \Exception('Not implemented');
    }

    public function write(string $path, string $contents, Config $config): void
    {
        throw new \Exception('Not implemented');
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        throw new \Exception('Not implemented');
    }

    public function read(string $path): string
    {
        return $this->symfonyFilesystem->readFile('https://' . $path);
    }

    public function readStream(string $path)
    {
        throw new \Exception('Not implemented');
    }

    public function delete(string $path): void
    {
        throw new \Exception('Not implemented');
    }

    public function deleteDirectory(string $path): void
    {
        throw new \Exception('Not implemented');
    }

    public function createDirectory(string $path, Config $config): void
    {
        throw new \Exception('Not implemented');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw new \Exception('Not implemented');
    }

    public function visibility(string $path): FileAttributes
    {
        throw new \Exception('Not implemented');
    }

    public function mimeType(string $path): FileAttributes
    {
        throw new \Exception('Not implemented');
    }

    public function lastModified(string $path): FileAttributes
    {
        throw new \Exception('Not implemented');
    }

    public function fileSize(string $path): FileAttributes
    {
        throw new \Exception('Not implemented');
    }

    public function listContents(string $path, bool $deep): iterable
    {
        throw new \Exception('Not implemented');
    }

    public function move(string $source, string $destination, Config $config): void
    {
        throw new \Exception('Not implemented');
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw new \Exception('Not implemented');
    }
}
