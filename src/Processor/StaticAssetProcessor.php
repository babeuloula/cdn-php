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

namespace BaBeuloula\CdnPhp\Processor;

use League\Flysystem\FilesystemAdapter;
use MatthiasMullie\Minify;
use Psr\Log\LoggerInterface;

final class StaticAssetProcessor
{
    private const array MINIFIABLE_CSS = ['css'];
    private const array MINIFIABLE_JS = ['js'];
    private const array MINIFIABLE_JSON = ['json', 'webmanifest'];

    public function __construct(
        private readonly FilesystemAdapter $adapter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(string $path, string $extension): string
    {
        $content = $this->adapter->read($path);
        $extension = strtolower($extension);

        if (true === \in_array($extension, self::MINIFIABLE_CSS, true)) {
            $minifier = new Minify\CSS($content);
            $content = $minifier->minify();
            $this->logger->info('Minified CSS: {path}', ['path' => $path]);
        } elseif (true === \in_array($extension, self::MINIFIABLE_JS, true)) {
            $minifier = new Minify\JS($content);
            $content = $minifier->minify();
            $this->logger->info('Minified JS: {path}', ['path' => $path]);
        } elseif (true === \in_array($extension, self::MINIFIABLE_JSON, true)) {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            $content = json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            $this->logger->info('Minified JSON: {path}', ['path' => $path]);
        }

        return $content;
    }
}
