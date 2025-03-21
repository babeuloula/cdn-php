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

namespace BaBeuloula\CdnPhp\Decoder;

use BaBeuloula\CdnPhp\Dto\QueryParams;

final class UriDecoder
{
    private ?string $finalUri = null;
    private readonly string $imageUrl;
    private readonly string $domain;
    private readonly string $filename;
    private readonly QueryParams $query;

    /** @param string[] $domainsAliases */
    public function __construct(private readonly string $uri, private readonly array $domainsAliases = [])
    {
        $this->imageUrl = explode('?', $this->getUri())[0];
        $this->domain = (string) parse_url($this->getUri(), PHP_URL_HOST);
        $this->filename = basename((string) parse_url($this->getUri(), PHP_URL_PATH));

        parse_str((string) parse_url($this->getUri(), PHP_URL_QUERY), $query);
        /** @var array<string, string> $query */
        $this->query = QueryParams::fromArray($query);
    }

    public function getUri(): string
    {
        if (null === $this->finalUri) {
            $uri = ltrim($this->uri, '/');

            if (true === str_starts_with($uri, '_')) {
                $domainAlias = trim(explode('/', $uri)[0], '_');
                $domain = $this->domainsAliases[$domainAlias] ?? '';
                $uri = str_replace("_{$domainAlias}_", $domain, $uri);
            }

            $uri = str_replace(['www.', 'http://', 'http:/', 'https://', 'https:/'], '', $uri);

            $this->finalUri = "https://$uri";
        }

        return $this->finalUri;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getExtension(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    public function getParams(): QueryParams
    {
        return $this->query;
    }
}
