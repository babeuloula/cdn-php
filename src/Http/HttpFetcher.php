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

namespace BaBeuloula\CdnPhp\Http;

use BaBeuloula\CdnPhp\Exception\FileTooLargeException;
use BaBeuloula\CdnPhp\Exception\SsrfAttemptException;
use BaBeuloula\CdnPhp\Security\SsrfValidator;

class HttpFetcher
{
    public function __construct(
        private readonly int $timeout,
        private readonly int $maxBytes,
        private readonly bool $allowRedirects = false,
        private readonly ?SsrfValidator $ssrfValidator = null,
    ) {
    }

    /**
     * @throws \RuntimeException
     * @throws FileTooLargeException
     * @throws SsrfAttemptException
     */
    public function fetch(string $url): string
    {
        if (null !== $this->ssrfValidator) {
            $this->ssrfValidator->assertSafe($url);
        }

        $followLocation = (true === $this->allowRedirects) ? 1 : 0;
        $context = stream_context_create(
            [
                'http' => [
                    'timeout' => $this->timeout,
                    'follow_location' => $followLocation,
                    'max_redirects' => (true === $this->allowRedirects) ? 5 : 0,
                ],
                'https' => ['timeout' => $this->timeout],
            ],
        );

        set_error_handler(static fn (): bool => true);
        try {
            $content = file_get_contents($url, false, $context, offset: 0, length: max(1, $this->maxBytes + 1));
        } finally {
            restore_error_handler();
        }

        if (false === $content) {
            throw new \RuntimeException("Failed to fetch URL: {$url}");
        }

        if (\strlen($content) > $this->maxBytes) {
            throw new FileTooLargeException($url, $this->maxBytes);
        }

        return $content;
    }
}
