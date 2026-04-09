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
use BaBeuloula\CdnPhp\Exception\FileTooLargeException;
use BaBeuloula\CdnPhp\Exception\InvalidUriException;
use BaBeuloula\CdnPhp\Exception\NotAllowedDomainException;
use BaBeuloula\CdnPhp\Exception\NotSupportedExtensionException;
use BaBeuloula\CdnPhp\Processor\ImageProcessor;
use BaBeuloula\CdnPhp\Processor\PathProcessor;
use BaBeuloula\CdnPhp\Processor\StaticAssetProcessor;
use BaBeuloula\CdnPhp\Storage\Storage;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Cdn
{
    /** @var string[] */
    private const array IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'heic'];

    /** @var string[] */
    private const array STATIC_EXTENSIONS = [
        'css', 'js', 'woff', 'woff2', 'ttf', 'eot', 'otf', 'svg', 'ico',
        'xml', 'json', 'webmanifest', 'txt', 'map',
    ];

    /**
     * @param string[] $allowedDomains
     * @param string[] $domainsAliases
     */
    public function __construct(
        private readonly array $allowedDomains,
        private readonly array $domainsAliases,
        private readonly Storage $storage,
        private readonly ImageProcessor $imageProcessor,
        private readonly StaticAssetProcessor $staticAssetProcessor,
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
        private readonly string $forceToken = '',
    ) {
    }

    public function handleRequest(Request $request): Response
    {
        if (false === $request->isMethod(Request::METHOD_GET)) {
            return new Response('Only GET request is supported.', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $decoder = new UriDecoder($request->getRequestUri(), $this->domainsAliases);

        try {
            $this->validate($decoder);
        } catch (EmptyUriException) {
            return new Response('Welcome to your CDN PHP (https://github.com/babeuloula/cdn-php)', Response::HTTP_OK);
        } catch (InvalidUriException | NotSupportedExtensionException | NotAllowedDomainException $e) {
            return new Response($e->getMessage(), $e->getCode());
        }

        $extension = mb_strtolower(pathinfo($decoder->getImageUrl(), PATHINFO_EXTENSION));
        $isImage = \in_array($extension, self::IMAGE_EXTENSIONS, true);

        $pathProcessor = new PathProcessor($decoder);

        [$supportAvif, $supportWebp] = $this->detectOutputFormat($request, $isImage);

        $cachedPath = $pathProcessor->getPath($supportAvif, $supportWebp);
        $force = $this->resolveForce($request);

        if (false === $this->storage->exists($cachedPath) || true === $force) {
            if (true === $force) {
                $this->logger->info('Force re-fetch: {url}', ['url' => $decoder->getImageUrl()]);
            }

            $errorResponse = $this->fetchAndCache(
                $decoder,
                $extension,
                $isImage,
                $supportAvif,
                $supportWebp,
                $cachedPath,
                $force,
            );
            if (null !== $errorResponse) {
                return $errorResponse;
            }
        }

        $this->logger->debug('Serve: {cachedPath}', ['cachedPath' => $cachedPath]);

        return $this->cache->createResponse($cachedPath, $supportAvif, $supportWebp, $request, varyAccept: $isImage);
    }

    private function fetchAndCache(
        UriDecoder $decoder,
        string $extension,
        bool $isImage,
        bool $supportAvif,
        bool $supportWebp,
        string $cachedPath,
        bool $force,
    ): ?Response {
        try {
            $originalPath = $this->storage->fetchFile($decoder->getImageUrl(), $decoder->getDomain(), $force);
        } catch (FileTooLargeException | FileNotFoundException $e) {
            return new Response($e->getMessage(), $e->getCode());
        }

        if (true === $isImage) {
            return $this->cacheImage($originalPath, $decoder, $supportAvif, $supportWebp, $cachedPath);
        }

        return $this->cacheStaticAsset($originalPath, $extension, $cachedPath);
    }

    private function cacheImage(
        string $originalPath,
        UriDecoder $decoder,
        bool $supportAvif,
        bool $supportWebp,
        string $cachedPath,
    ): ?Response {
        try {
            $processedImage = $this->imageProcessor->process(
                $originalPath,
                $decoder->getParams(),
                $supportAvif,
                $supportWebp,
            );
            $this->storage->save($cachedPath, $this->storage->read($processedImage));

            return null;
        } catch (\Throwable $e) {
            $this->logger->error(
                'Image processing failed: {message}',
                ['message' => $e->getMessage(), 'exception' => $e],
            );

            return new Response('Image processing failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function cacheStaticAsset(string $originalPath, string $extension, string $cachedPath): ?Response
    {
        try {
            $processedContent = $this->staticAssetProcessor->process($originalPath, $extension);
            $this->storage->save($cachedPath, $processedContent);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error(
                'Static asset processing failed: {message}',
                ['message' => $e->getMessage(), 'exception' => $e],
            );

            return new Response('Static asset processing failed.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return array{bool, bool}
     */
    private function detectOutputFormat(Request $request, bool $isImage): array
    {
        if (false === $isImage) {
            return [false, false];
        }

        $supportAvif = $this->supportsAvif($request);

        if (true === $supportAvif) {
            return [true, false];
        }

        return [false, $this->supportsWebp($request)];
    }

    private function supportsAvif(Request $request): bool
    {
        return true === str_contains((string) $request->headers->get('Accept'), 'image/avif');
    }

    private function supportsWebp(Request $request): bool
    {
        return true === str_contains((string) $request->headers->get('Accept'), 'image/webp');
    }

    private function resolveForce(Request $request): bool
    {
        if (false === $request->query->getBoolean('force')) {
            return false;
        }
        if ('' !== $this->forceToken && $request->query->get('token') !== $this->forceToken) {
            return false;
        }
        return true;
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
        $allExtensions = [...self::IMAGE_EXTENSIONS, ...self::STATIC_EXTENSIONS];
        if (false === \in_array($extension, $allExtensions, true)) {
            throw new NotSupportedExtensionException($extension);
        }

        if (true === \in_array($extension, self::IMAGE_EXTENSIONS, true)
            && null !== $decoder->getParams()->watermarkUrl
        ) {
            $watermarkDomain = parse_url('https://' . $decoder->getParams()->watermarkUrl, PHP_URL_HOST);
            if (false === \is_string($watermarkDomain)
                || false === \in_array($watermarkDomain, $this->allowedDomains, true)
            ) {
                throw new NotAllowedDomainException($decoder->getParams()->watermarkUrl);
            }
        }
    }
}
