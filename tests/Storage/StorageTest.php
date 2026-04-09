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

namespace BaBeuloula\CdnPhp\Tests\Storage;

use BaBeuloula\CdnPhp\Decoder\UriDecoder;
use BaBeuloula\CdnPhp\Exception\FileNotFoundException;
use BaBeuloula\CdnPhp\Storage\Storage;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StorageTest extends TestCase
{
    private UriDecoder $decoder;
    private Storage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decoder = new UriDecoder(static::TEST_BASE_URI);

        /** @var Storage $storage */
        $storage = $this->getContainer(Storage::class);

        $this->storage = $storage;
    }


    #[Test]
    public function canFetchAnImage(): void
    {
        static::assertSame(
            static::TEST_ORIGINAL_PATH,
            $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain()),
        );
    }

    #[Test]
    public function canFetchAnExistingImage(): void
    {
        $this->storage->save(static::TEST_ORIGINAL_PATH, 'foo_content');

        static::assertSame(
            static::TEST_ORIGINAL_PATH,
            $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain()),
        );
    }

    #[Test]
    public function canForceFetchAnAlreadyCachedImage(): void
    {
        $this->storage->save(static::TEST_ORIGINAL_PATH, 'stale_content');

        $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain(), true);

        static::assertNotSame('stale_content', $this->storage->read(static::TEST_ORIGINAL_PATH));
    }

    #[Test]
    public function cantFetchANotfoundImage(): void
    {
        $decoder = new UriDecoder('http://example.com/not-found.jpg');

        /** @var Storage $storage */
        $storage = $this->getContainer(Storage::class);

        static::expectException(FileNotFoundException::class);
        $storage->fetchFile($decoder->getImageUrl(), $decoder->getDomain());
    }

    #[Test]
    public function canReadAnImage(): void
    {
        $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain());

        static::assertIsString($this->storage->read(static::TEST_ORIGINAL_PATH));
    }

    #[Test]
    public function canReadAsStreamAnImage(): void
    {
        $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain());

        static::assertIsResource($this->storage->readStream(static::TEST_ORIGINAL_PATH));
    }

    #[Test]
    public function canGetMimetypeOfAnImage(): void
    {
        $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain());

        static::assertSame(
            'image/jpeg',
            $this->storage->mimeType(static::TEST_ORIGINAL_PATH),
        );
    }

    #[Test]
    public function canGetFilesizeOfAnImage(): void
    {
        $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain());

        static::assertGreaterThan(0, $this->storage->fileSize(static::TEST_ORIGINAL_PATH));
    }

    #[Test]
    public function canGetLastModifiedOfAnImage(): void
    {
        $this->storage->fetchFile($this->decoder->getImageUrl(), $this->decoder->getDomain());

        static::assertGreaterThan(0, $this->storage->lastModified(static::TEST_ORIGINAL_PATH));
    }
}
