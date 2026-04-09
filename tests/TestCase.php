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
use BaBeuloula\CdnPhp\Exception\FileTooLargeException;
use BaBeuloula\CdnPhp\Http\HttpFetcher;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected const string TEST_BASE_URI = 'https://example.com/image.jpg';
    protected const string TEST_BASE_URI_ALIAS = '_ex_ample_/image.jpg';
    protected const string TEST_WATERMARK_URL = 'https://example.com/watermark.jpg';
    protected const string TEST_TOO_LARGE_URI = 'https://example.com/too-large.jpg';
    protected const string TEST_CORRUPT_IMAGE_URI = 'https://example.com/corrupt.jpg';
    protected const string TEST_GIF_BASE_URI = 'https://example.com/image.gif';
    protected const string TEST_ANIMATED_WEBP_BASE_URI = 'https://example.com/animated.webp';
    protected const string TEST_ANIMATED_WEBP_FILENAME = 'animated.webp';
    protected const string TEST_ANIMATED_WEBP_CACHE_PATH = './cache/animated.webp';
    protected const string TEST_FORCE_TOKEN = 'test-force-token';
    protected const string TEST_DOMAIN = 'example.com';
    protected const string TEST_DOMAIN_ALIAS = 'ex_ample';
    protected const string TEST_FILENAME = 'image.jpg';
    protected const string TEST_FILENAME_MD5 = '18867d45576d8283d6fabb82406789c8.jpg';
    protected const string TEST_EXTENSION = 'jpg';
    protected const string TEST_ORIGINAL_PATH = 'example.com/original/' . self::TEST_FILENAME_MD5;
    protected const string TEST_CACHE_PATH = './cache/image.jpg/4be3b730cab4c047525c594c7560cbf0';
    protected const string TEST_GIF_FILENAME = 'image.gif';
    protected const string TEST_GIF_CACHE_PATH = './cache/image.gif';
    protected const string TEST_GIF_WEBP_CACHE_PATH = './cache/image.webp';
    protected const string TEST_CSS_URI = 'https://example.com/style.css';
    protected const string TEST_CSS_FILENAME = 'style.css';
    protected const string TEST_JS_URI = 'https://example.com/app.js';
    protected const string TEST_JS_FILENAME = 'app.js';
    protected const string TEST_WOFF2_URI = 'https://example.com/font.woff2';
    protected const string TEST_WOFF2_FILENAME = 'font.woff2';
    protected const string TEST_XML_URI = 'https://example.com/sitemap.xml';
    protected const string TEST_JSON_URI = 'https://example.com/manifest.json';
    protected const string TEST_WEBMANIFEST_URI = 'https://example.com/app.webmanifest';
    protected const string TEST_TXT_URI = 'https://example.com/robots.txt';
    protected const string TEST_MAP_URI = 'https://example.com/app.js.map';
    protected const string TEST_HEIC_URI = 'https://example.com/photo.heic';
    protected const string TEST_WASM_URI = 'https://example.com/module.wasm';
    protected const string TEST_CORRUPT_JSON_URI = 'https://example.com/broken.json';

    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $httpFetcherMock = $this->createStub(HttpFetcher::class);

        $httpFetcherMock
            ->method('fetch')
            ->willReturnCallback(
                static function (string $url) {
                    if (static::TEST_BASE_URI === $url) {
                        return static::getTestImageContent();
                    }

                    if (static::TEST_GIF_BASE_URI === $url) {
                        return static::getTestAnimatedGifContent();
                    }

                    if (static::TEST_ANIMATED_WEBP_BASE_URI === $url) {
                        return static::getTestAnimatedWebpContent();
                    }

                    if (static::TEST_CORRUPT_IMAGE_URI === $url) {
                        return 'not-an-image-data';
                    }

                    if (static::TEST_TOO_LARGE_URI === $url) {
                        throw new FileTooLargeException($url, 1);
                    }

                    if (static::TEST_CSS_URI === $url) {
                        return static::getTestCssContent();
                    }

                    if (static::TEST_JS_URI === $url) {
                        return static::getTestJsContent();
                    }

                    if (static::TEST_WOFF2_URI === $url) {
                        return static::getTestFontContent();
                    }

                    if (static::TEST_XML_URI === $url) {
                        return static::getTestXmlContent();
                    }

                    if (static::TEST_JSON_URI === $url) {
                        return static::getTestJsonContent();
                    }

                    if (static::TEST_WEBMANIFEST_URI === $url) {
                        return static::getTestWebmanifestContent();
                    }

                    if (static::TEST_TXT_URI === $url) {
                        return static::getTestTxtContent();
                    }

                    if (static::TEST_MAP_URI === $url) {
                        return static::getTestMapContent();
                    }

                    if (static::TEST_WASM_URI === $url) {
                        return static::getTestWasmContent();
                    }

                    if (static::TEST_CORRUPT_JSON_URI === $url) {
                        return '{not valid json';
                    }

                    throw new \RuntimeException("URL not mocked: {$url}");
                },
            )
        ;

        $this->container = new Container();
        $this->container->add(HttpFetcher::class, $httpFetcherMock);
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

    protected static function getTestCssContent(): string
    {
        return "/* comment */ body { color: red; }\n\np { margin: 0; }";
    }

    protected static function getTestJsContent(): string
    {
        return "// comment\nfunction hello() { return 'world'; }";
    }

    protected static function getTestFontContent(): string
    {
        return 'woff2-binary-font-content';
    }

    protected static function getTestXmlContent(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset><url><loc>https://example.com/</loc></url></urlset>';
    }

    protected static function getTestJsonContent(): string
    {
        return "{\n  \"name\": \"App\",\n  \"version\": \"1\"\n}";
    }

    protected static function getTestWebmanifestContent(): string
    {
        return "{\n  \"name\": \"App\",\n  \"icons\": []\n}";
    }

    protected static function getTestTxtContent(): string
    {
        return "User-agent: *\nDisallow:";
    }

    protected static function getTestMapContent(): string
    {
        return '{"version":3,"sources":["app.js"],"mappings":""}';
    }

    protected static function getTestWasmContent(): string
    {
        return "\x00asm\x01\x00\x00\x00";
    }

    protected static function getTestAnimatedGifContent(): string
    {
        $animation = new \Imagick();

        foreach (['red', 'blue'] as $color) {
            $frame = new \Imagick();
            $frame->newImage(10, 10, new \ImagickPixel($color), 'gif');
            $frame->setImageDelay(10);
            $animation->addImage($frame);
            $frame->clear();
        }

        $animation->setFormat('gif');
        $content = $animation->getImagesBlob();
        $animation->clear();

        return $content;
    }

    protected static function getTestAnimatedWebpContent(): string
    {
        $animation = new \Imagick();

        foreach (['red', 'blue'] as $color) {
            $frame = new \Imagick();
            $frame->newImage(10, 10, new \ImagickPixel($color), 'webp');
            $frame->setImageDelay(10);
            $animation->addImage($frame);
            $frame->clear();
        }

        $animation->setFormat('WEBP');
        $content = $animation->getImagesBlob();
        $animation->clear();

        return $content;
    }
}
