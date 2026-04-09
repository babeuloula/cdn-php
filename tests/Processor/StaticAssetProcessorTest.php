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

use BaBeuloula\CdnPhp\Processor\StaticAssetProcessor;
use BaBeuloula\CdnPhp\Tests\TestCase;
use League\Flysystem\Config;
use League\Flysystem\FilesystemAdapter;
use PHPUnit\Framework\Attributes\Test;

class StaticAssetProcessorTest extends TestCase
{
    private FilesystemAdapter $adapter;
    private StaticAssetProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var FilesystemAdapter $adapter */
        $adapter = $this->getContainer(FilesystemAdapter::class);
        $this->adapter = $adapter;

        $this->adapter->write(static::TEST_CSS_FILENAME, static::getTestCssContent(), new Config());
        $this->adapter->write(static::TEST_JS_FILENAME, static::getTestJsContent(), new Config());
        $this->adapter->write(static::TEST_WOFF2_FILENAME, static::getTestFontContent(), new Config());
        $this->adapter->write('manifest.json', static::getTestJsonContent(), new Config());
        $this->adapter->write('app.webmanifest', static::getTestWebmanifestContent(), new Config());

        /** @var StaticAssetProcessor $processor */
        $processor = $this->getContainer(StaticAssetProcessor::class);
        $this->processor = $processor;
    }

    #[Test]
    public function canMinifyCss(): void
    {
        $result = $this->processor->process(static::TEST_CSS_FILENAME, 'css');

        static::assertStringNotContainsString('/* comment */', $result);
        static::assertStringContainsString('color:red', $result);
        static::assertStringContainsString('margin:0', $result);
    }

    #[Test]
    public function canMinifyJs(): void
    {
        $result = $this->processor->process(static::TEST_JS_FILENAME, 'js');

        static::assertStringNotContainsString('// comment', $result);
        static::assertStringContainsString('function hello()', $result);
    }

    #[Test]
    public function canMinifyCssWithUppercaseExtension(): void
    {
        $result = $this->processor->process(static::TEST_CSS_FILENAME, 'CSS');

        static::assertStringNotContainsString('/* comment */', $result);
        static::assertStringContainsString('color:red', $result);
    }

    #[Test]
    public function canPassthroughFontAsset(): void
    {
        $result = $this->processor->process(static::TEST_WOFF2_FILENAME, 'woff2');

        static::assertSame(static::getTestFontContent(), $result);
    }

    #[Test]
    public function canMinifyJson(): void
    {
        $result = $this->processor->process('manifest.json', 'json');

        static::assertStringNotContainsString("\n", $result);
        static::assertStringContainsString('"name":', $result);
    }

    #[Test]
    public function canMinifyWebmanifest(): void
    {
        $result = $this->processor->process('app.webmanifest', 'webmanifest');

        static::assertStringNotContainsString("\n", $result);
        static::assertStringContainsString('"name":', $result);
    }
}
