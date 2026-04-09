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

namespace BaBeuloula\CdnPhp\Cache;

use BaBeuloula\CdnPhp\Storage\Storage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Cache
{
    public function __construct(
        private readonly Storage $storage,
        private readonly int $ttl,
    ) {
    }

    public function createResponse(
        string $path,
        bool $supportAvif,
        bool $supportWebp,
        Request $request,
        bool $varyAccept = true,
    ): Response {
        $lastModified = (new \DateTimeImmutable())->setTimestamp($this->storage->lastModified($path));

        $contentType = $this->storage->mimeType($path);
        if (true === $supportAvif) {
            $contentType = 'image/avif';
        } elseif (true === $supportWebp) {
            $contentType = 'image/webp';
        }

        $response = new StreamedResponse();
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Length', (string) $this->storage->fileSize($path));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic();

        if (true === $varyAccept) {
            $response->headers->set('Vary', 'Accept');
        }
        $response->setMaxAge($this->ttl);
        $response->setExpires((new \DateTimeImmutable())->modify("+$this->ttl seconds"));
        $response->setLastModified($lastModified);
        $response->setEtag(
            md5($path . ':' . $this->storage->fileSize($path) . ':' . $lastModified->getTimestamp()),
        );
        $response->isNotModified($request);

        $response->setCallback(
            function () use ($path) {
                // @codeCoverageIgnoreStart
                $stream = $this->storage->readStream($path);
                try {
                    if (0 !== ftell($stream)) {
                        rewind($stream);
                    }
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
                // @codeCoverageIgnoreEnd
            },
        );

        return $response;
    }
}
