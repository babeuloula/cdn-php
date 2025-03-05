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
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

final class Container
{
    /** @var array<string, mixed> */
    private array $container = [];

    public function boot(): void
    {
        $this->add(SymfonyFilesystem::class, new SymfonyFilesystem());

        $this->bootDomains();
        $this->bootLogger();
        $this->bootStorage();
        $this->bootImageProcessor();
        $this->bootCache();

        $this->add(
            Cdn::class,
            new Cdn(
                $this->get('allowed_domains'),
                $this->get('domains_aliases'),
                $this->get(Storage::class),
                $this->get(ImageProcessor::class),
                $this->get(Cache::class),
                $this->get(LoggerInterface::class),
            ),
        );
    }

    public function add(string $key, mixed $value): void
    {
        if (true === \array_key_exists($key, $this->container)) {
            return;
        }

        $this->container[$key] = $value;
    }

    public function get(string $key): mixed
    {
        if (false === \array_key_exists($key, $this->container)) {
            throw new \InvalidArgumentException("Key '{$key}' does not exist.");
        }

        return $this->container[$key];
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

    private function bootLogger(): void
    {
        $this->add(
            LoggerInterface::class,
            new BrefLogger(
                $this->getEnv('LOG_LEVEL'),
                $this->getEnv('LOG_STREAM'),
            ),
        );
    }

    private function bootDomains(): void
    {
        $domainsAliases = [];
        foreach (explode(',', $this->getEnv('DOMAINS_ALIASES')) as $domain) {
            if (false === str_contains($domain, '=')) {
                throw new \InvalidArgumentException("Domain alias must contain '='.");
            }

            $parts = explode('=', $domain);
            $domainsAliases[$parts[1]] = $parts[0];
        }
        $this->add('domains_aliases', $domainsAliases);

        $this->add('allowed_domains', explode(',', $this->getEnv('ALLOWED_DOMAINS')));
    }

    private function bootStorage(): void
    {
        $this->add('storage_driver', $this->getEnv('STORAGE_DRIVER'));
        $this->add('storage_path', $this->getEnv('STORAGE_PATH'));
        switch ($this->get('storage_driver')) {
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
                    ],
                );

                $this->add(
                    FilesystemAdapter::class,
                    new AwsS3V3Adapter(
                        client: $awsClient,
                        bucket: $this->getEnv('S3_BUCKET'),
                        visibility: new PortableVisibilityConverter(Visibility::PRIVATE),
                    ),
                );
                break;

            case 'local':
                $this->add(
                    FilesystemAdapter::class,
                    new LocalFilesystemAdapter(
                        $this->get('storage_path'),
                    ),
                );
                break;

            default:
                throw new \InvalidArgumentException("Unsupported storage driver '{$this->get('storage_driver')}'.");
        }

        $this->add(
            UrlFilesystemAdapter::class,
            new UrlFilesystemAdapter(
                $this->get(SymfonyFilesystem::class),
            ),
        );
        $this->add(LeagueFilesystem::class, new LeagueFilesystem($this->get(FilesystemAdapter::class)));
        $this->add(
            Storage::class,
            new Storage(
                $this->get(LeagueFilesystem::class),
                $this->get(SymfonyFilesystem::class),
                $this->get(LoggerInterface::class),
            ),
        );
    }

    private function bootCache(): void
    {
        $this->add('cache_ttl', (int) $this->getEnv('CACHE_TTL'));
        $this->add(
            Cache::class,
            new Cache(
                $this->get(Storage::class),
                $this->get('cache_ttl'),
            ),
        );
    }

    private function bootImageProcessor(): void
    {
        $this->add('image_compression', (int) $this->getEnv('IMAGE_COMPRESSION'));
        $this->add(
            ImageProcessor::class,
            new ImageProcessor(
                $this->get(FilesystemAdapter::class),
                $this->get(UrlFilesystemAdapter::class),
                $this->get(LoggerInterface::class),
                $this->get('image_compression'),
            ),
        );
    }
}
