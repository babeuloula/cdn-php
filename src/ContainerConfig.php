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

use Aws\S3\S3Client;
use BaBeuloula\CdnPhp\Cache\Cache;
use BaBeuloula\CdnPhp\Flysystem\Adapter\UrlFilesystemAdapter;
use BaBeuloula\CdnPhp\Processor\ImageProcessor;
use BaBeuloula\CdnPhp\Storage\Storage;
use Bref\Logger\StderrLogger as BrefLogger;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;
use Pimple\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class ContainerConfig extends Container
{
    public function __construct()
    {
        parent::__construct();

        $this['storage_driver'] = $this->getEnv('STORAGE_DRIVER');
        $this['storage_path'] = $this->getEnv('STORAGE_PATH');
        $this['cache_ttl'] = (int) $this->getEnv('CACHE_TTL');

        $domainsAliases = [];
        foreach (explode(',', $this->getEnv('DOMAINS_ALIASES')) as $domain) {
            if (false === str_contains($domain, '=')) {
                throw new \InvalidArgumentException("Domain alias must contain '='.");
            }

            $parts = explode('=', $domain);
            $domainsAliases[$parts[1]] = $parts[0];
        }
        $this['domains_aliases'] = $domainsAliases;

        $this['allowed_domains'] = explode(',', $this->getEnv('ALLOWED_DOMAINS'));

        $this['image_compression'] = (int) $this->getEnv('IMAGE_COMPRESSION');

        $this[LoggerInterface::class] = fn () => new BrefLogger(
            $this->getEnv('LOG_LEVEL'),
            $this->getEnv('LOG_STREAM')
        );

        $this[SymfonyFilesystem::class] = static fn() => new SymfonyFilesystem();

        switch ($this['storage_driver']) {
            case 's3':
                $awsClient = new S3Client(
                    [
                        'version' => $this->getEnv('S3_VERSION', 'latest'),
                        'region' => $this->getEnv('S3_REGION'),
                        'endpoint' => $this->getEnv('S3_ENDPOINT'),
                        'use_path_style_endpoint' => 1 === ((int) $this->getEnv('S3_PATH_STYLE_ENDPOINT', 1)),
                        'credentials' => [
                            'key' => $this->getEnv('S3_ACCESS_KEY'),
                            'secret' => $this->getEnv('S3_SECRET_KEY'),
                        ],
                    ]
                );

                $this[FilesystemAdapter::class] = new AwsS3V3Adapter(
                    client: $awsClient,
                    bucket: $this->getEnv('S3_BUCKET'),
                    visibility: new PortableVisibilityConverter(Visibility::PRIVATE),
                );
                break;

            case 'local':
                $this[FilesystemAdapter::class] = static fn(self $c) => new LocalFilesystemAdapter($c['storage_path']);
                break;

            default:
                throw new \InvalidArgumentException("Unsupported storage driver '{$this['storage_driver']}'.");
        }

        $this[UrlFilesystemAdapter::class] = static fn(self $c) => new UrlFilesystemAdapter(
            $c[SymfonyFilesystem::class],
        );
        $this[LeagueFilesystem::class] = static fn(self $c) => new LeagueFilesystem($c[FilesystemAdapter::class]);
        $this[Storage::class] = static fn (self $c) => new Storage(
            $c[LeagueFilesystem::class],
            $c[SymfonyFilesystem::class],
            $c[LoggerInterface::class],
        );

        $this[ImageProcessor::class] = static fn(self $c) => new ImageProcessor(
            $c[FilesystemAdapter::class],
            $c[UrlFilesystemAdapter::class],
            $c[LoggerInterface::class],
            $c['image_compression'],
        );

        $this[Cache::class] = static fn(self $c) => new Cache($c[Storage::class], $c['cache_ttl']);

        $this[Cdn::class] = static fn(self $c) => new Cdn(
            $c['allowed_domains'],
            $c['domains_aliases'],
            $c[Storage::class],
            $c[ImageProcessor::class],
            $c[Cache::class],
            $c[LoggerInterface::class],
        );
    }

    private function getEnv(string $key, mixed $default = null): string
    {
        // phpcs:ignore
        if (false === \array_key_exists($key, $_ENV)) {
            return $default;
        }

        // phpcs:ignore
        return (string) $_ENV[$key];
    }
}
