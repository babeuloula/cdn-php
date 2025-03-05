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

namespace BaBeuloula\CdnPhp\Tests;

use BaBeuloula\CdnPhp\Container;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

class TestCase extends BaseTestCase
{
    protected const string TEST_BASE_URI = 'https://example.com/image.jpg';
    protected const string TEST_BASE_URI_ALIAS = '_e_/image.jpg';
    protected const string TEST_WATERMARK_URL = 'https://example.com/watermark.jpg';
    protected const string TEST_DOMAIN = 'example.com';
    protected const string TEST_DOMAIN_ALIAS = 'e';
    protected const string TEST_FILENAME = 'image.jpg';
    protected const string TEST_FILENAME_MD5 = '18867d45576d8283d6fabb82406789c8.jpg';
    protected const string TEST_EXTENSION = 'jpg';
    protected const string TEST_ORIGINAL_PATH = 'example.com/original/' . self::TEST_FILENAME_MD5;
    protected const string TEST_CACHE_PATH = './cache/image.jpg/4be3b730cab4c047525c594c7560cbf0';

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $symfonyFilesystemMock = $this->createPartialMock(SymfonyFilesystem::class, ['readFile']);

        $symfonyFilesystemMock
            ->method('readFile')
            ->willReturnCallback(
                static function (string $path) {
                    if (static::TEST_BASE_URI === $path) {
                        return static::getTestImageContent();
                    }

                    throw new IOException('Testing error.');
                },
            )
        ;

        $this->container = new Container();
        $this->container->add(SymfonyFilesystem::class, $symfonyFilesystemMock);
        $this->container->add(FilesystemAdapter::class, new InMemoryFilesystemAdapter());
        $this->container->boot();
    }

    protected function getContainer(string $id): mixed
    {
        return $this->container->get($id);
    }

    protected function getQueryParameters(): string
    {
        return '?' . http_build_query(
            [
                'w' => 300,
                'h' => 200,
                'wu' => static::TEST_WATERMARK_URL,
                'ws' => 100,
                'wo' => 100,
            ],
        );
    }

    protected static function getTestImageContent(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/image.jpg');
    }
}
