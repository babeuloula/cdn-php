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
    private string $imageUrl;
    private string $domain;
    private string $filename;
    private QueryParams $query;

    public function __construct(private readonly string $uri)
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
        return ltrim($this->uri, '/');
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
