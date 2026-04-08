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

use BaBeuloula\CdnPhp\Http\HttpFetcher;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;

final class UrlFilesystemAdapter implements FilesystemAdapter
{
    public function __construct(private readonly HttpFetcher $httpFetcher)
    {
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->httpFetcher->fetch('https://' . $path);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
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
        return $this->httpFetcher->fetch('https://' . $path);
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
