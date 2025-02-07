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

    public function createResponse(string $path, bool $supportWebp, Request $request): Response
    {
        $stream = $this->storage->readStream($path);

        $now = new \DateTime();

        $response = new StreamedResponse();
        $response->headers->set(
            'Content-Type',
            (true === $supportWebp) ? 'image/webp' : $this->storage->mimeType($path),
        );
        $response->headers->set('Content-Length', (string) $this->storage->fileSize($path));
        $response->setPublic();
        $response->setMaxAge($this->ttl);
        $response->setExpires($now->modify("+$this->ttl seconds"));
        $response->setLastModified($now->setTimestamp($this->storage->lastModified($path)));
        $response->isNotModified($request);

        $response->setCallback(
            static function () use ($stream) {
                // @codeCoverageIgnoreStart
                if (0 !== ftell($stream)) {
                    rewind($stream);
                }
                fpassthru($stream);
                fclose($stream);
                // @codeCoverageIgnoreEnd
            }
        );

        return $response;
    }
}
