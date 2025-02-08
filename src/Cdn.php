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

namespace BaBeuloula\CdnPhp;

use BaBeuloula\CdnPhp\Cache\Cache;
use BaBeuloula\CdnPhp\Decoder\UriDecoder;
use BaBeuloula\CdnPhp\Exception\EmptyUriException;
use BaBeuloula\CdnPhp\Exception\FileNotFoundException;
use BaBeuloula\CdnPhp\Exception\InvalidUriException;
use BaBeuloula\CdnPhp\Exception\NotAllowedDomainException;
use BaBeuloula\CdnPhp\Exception\NotSupportedExtensionException;
use BaBeuloula\CdnPhp\Processor\ImageProcessor;
use BaBeuloula\CdnPhp\Processor\PathProcessor;
use BaBeuloula\CdnPhp\Storage\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Cdn
{
    /** @param string[] $allowedDomains */
    public function __construct(
        private readonly array $allowedDomains,
        private readonly Storage $storage,
        private readonly ImageProcessor $imageProcessor,
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handleRequest(Request $request): Response
    {
        if (false === $request->isMethod(Request::METHOD_GET)) {
            return new Response('Only GET request is supported.', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $decoder = new UriDecoder($request->getRequestUri());

        try {
            $this->validate($decoder);
        } catch (EmptyUriException) {
            return new Response('Welcome to your CDN PHP (https://github.com/babeuloula/cdn-php)', Response::HTTP_OK);
        } catch (InvalidUriException | NotSupportedExtensionException | NotAllowedDomainException $e) {
            return new Response($e->getMessage(), $e->getCode());
        }

        $pathProcessor = new PathProcessor($decoder);

        $this->storage->setDecoder($decoder);

        $supportWebp = (true === str_contains((string) $request->headers->get('Accept'), 'image/webp'));

        $cachedPath = $pathProcessor->getPath($supportWebp);

        if (false === $this->storage->exists($cachedPath)) {
            try {
                $originalPath = $this->storage->fetchImage($decoder->getImageUrl());
            } catch (FileNotFoundException $e) {
                return new Response($e->getMessage(), $e->getCode());
            }

            $processedImage = $this->imageProcessor->process($originalPath, $decoder->getParams());
            $this->storage->save($cachedPath, $this->storage->read($processedImage));
        }

        $this->logger->info('Serve the image: {cachedPath}', ['cachedPath' => $cachedPath]);

        return $this->cache->createResponse($cachedPath, $supportWebp, $request);
    }

    /**
     * @throws EmptyUriException
     * @throws InvalidUriException
     * @throws NotAllowedDomainException
     * @throws NotSupportedExtensionException
     */
    private function validate(UriDecoder $decoder): void
    {
        if ('https://' === $decoder->getUri()) {
            throw new EmptyUriException();
        }

        if (false === filter_var($decoder->getUri(), FILTER_VALIDATE_URL)) {
            throw new InvalidUriException($decoder->getUri());
        }

        if (false === \in_array($decoder->getDomain(), $this->allowedDomains, true)) {
            throw  new NotAllowedDomainException($decoder->getDomain());
        }

        $extension = mb_strtolower(pathinfo($decoder->getImageUrl(), PATHINFO_EXTENSION));
        if (false === \in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            throw new NotSupportedExtensionException($extension);
        }
    }
}
