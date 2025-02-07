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
}
