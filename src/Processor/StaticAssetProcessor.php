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
            $content = $this->minifyJs($content, $path);
        } elseif (true === \in_array($extension, self::MINIFIABLE_JSON, true)) {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            $content = json_encode($decoded, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            $this->logger->info('Minified JSON: {path}', ['path' => $path]);
        }

        return $content;
    }

    /**
     * matthiasmullie/minify uses recursive PCRE backreferences (?-2) in its for-loop patterns (JS.php lines 406-408),
     * which can cause catastrophic backtracking on crafted input from an allowed domain (ReDoS).
     * Tightening the PCRE limits before minification caps CPU consumption; on failure we serve the unminified content.
     */
    private function minifyJs(string $content, string $path): string
    {
        $prevBacktrackLimit = (int) ini_get('pcre.backtrack_limit');
        $prevRecursionLimit = (int) ini_get('pcre.recursion_limit');
        ini_set('pcre.backtrack_limit', '100000');
        ini_set('pcre.recursion_limit', '500');

        $minifier = new Minify\JS($content);
        $minified = $minifier->minify();

        ini_set('pcre.backtrack_limit', (string) $prevBacktrackLimit);
        ini_set('pcre.recursion_limit', (string) $prevRecursionLimit);

        if (\PREG_NO_ERROR !== preg_last_error()) {
            $this->logger->warning(
                'JS minification failed (PCRE error {error}), serving unminified: {path}',
                ['error' => preg_last_error(), 'path' => $path],
            );

            return $content;
        }

        $this->logger->info('Minified JS: {path}', ['path' => $path]);

        return $minified;
    }
}
