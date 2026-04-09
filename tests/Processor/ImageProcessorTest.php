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

namespace BaBeuloula\CdnPhp\Tests\Processor;

use BaBeuloula\CdnPhp\Dto\QueryParams;
use BaBeuloula\CdnPhp\Processor\ImageProcessor;
use BaBeuloula\CdnPhp\Tests\TestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ImageProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $adapter->write(
            static::TEST_FILENAME,
            static::getTestImageContent(),
            new Config(),
        );
    }

    #[Test]
    public function canProcessImage(): void
    {
        /** @var ImageProcessor $imageProcessor */
        $imageProcessor = $this->getContainer(ImageProcessor::class);

        static::assertSame(
            static::TEST_CACHE_PATH,
            $imageProcessor->process('image.jpg', QueryParams::fromArray([]))
        );
    }

    #[Test]
    public function canProcessAnimatedGif(): void
    {
        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $adapter->write(
            static::TEST_GIF_FILENAME,
            static::getTestAnimatedGifContent(),
            new Config(),
        );

        /** @var ImageProcessor $imageProcessor */
        $imageProcessor = $this->getContainer(ImageProcessor::class);

        $resultPath = $imageProcessor->process(static::TEST_GIF_FILENAME, QueryParams::fromArray([]));

        static::assertSame(static::TEST_GIF_CACHE_PATH, $resultPath);

        $imagick = new \Imagick();
        $imagick->readImageBlob($adapter->read($resultPath));
        static::assertGreaterThan(1, $imagick->getNumberImages(), 'Animated GIF must preserve all frames');
        $imagick->clear();
    }

    #[Test]
    public function canProcessAnimatedGifWithResize(): void
    {
        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $adapter->write(
            static::TEST_GIF_FILENAME,
            static::getTestAnimatedGifContent(),
            new Config(),
        );

        /** @var ImageProcessor $imageProcessor */
        $imageProcessor = $this->getContainer(ImageProcessor::class);

        $resultPath = $imageProcessor->process(
            static::TEST_GIF_FILENAME,
            QueryParams::fromArray(['w' => 5])
        );

        static::assertSame(static::TEST_GIF_CACHE_PATH, $resultPath);

        $imagick = new \Imagick();
        $imagick->readImageBlob($adapter->read($resultPath));
        static::assertGreaterThan(1, $imagick->getNumberImages(), 'Animated GIF must preserve all frames after resize');
        static::assertLessThanOrEqual(5, $imagick->getImageWidth(), 'Width must be resized');
        $imagick->clear();
    }

    #[Test]
    public function canProcessAnimatedGifAsWebp(): void
    {
        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $adapter->write(
            static::TEST_GIF_FILENAME,
            static::getTestAnimatedGifContent(),
            new Config(),
        );

        /** @var ImageProcessor $imageProcessor */
        $imageProcessor = $this->getContainer(ImageProcessor::class);

        $resultPath = $imageProcessor->process(static::TEST_GIF_FILENAME, QueryParams::fromArray([]), true);

        static::assertSame(static::TEST_GIF_WEBP_CACHE_PATH, $resultPath);

        $imagick = new \Imagick();
        $imagick->readImageBlob($adapter->read($resultPath));
        static::assertGreaterThan(1, $imagick->getNumberImages(), 'Animated WebP must preserve all frames');
        static::assertSame('WEBP', $imagick->getImageFormat());
        $imagick->clear();
    }

    #[Test]
    public function canProcessAnimatedWebp(): void
    {
        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $adapter->write(
            static::TEST_ANIMATED_WEBP_FILENAME,
            static::getTestAnimatedWebpContent(),
            new Config(),
        );

        /** @var ImageProcessor $imageProcessor */
        $imageProcessor = $this->getContainer(ImageProcessor::class);

        $resultPath = $imageProcessor->process(static::TEST_ANIMATED_WEBP_FILENAME, QueryParams::fromArray([]));

        static::assertSame(static::TEST_ANIMATED_WEBP_CACHE_PATH, $resultPath);

        $imagick = new \Imagick();
        $imagick->readImageBlob($adapter->read($resultPath));
        static::assertGreaterThan(1, $imagick->getNumberImages(), 'Animated WebP must preserve all frames');
        static::assertSame('WEBP', $imagick->getImageFormat());
        $imagick->clear();
    }
}
