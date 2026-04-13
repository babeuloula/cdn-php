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
use BaBeuloula\CdnPhp\Http\HttpFetcher;
use BaBeuloula\CdnPhp\Processor\ImageProcessor;
use BaBeuloula\CdnPhp\Processor\StaticAssetProcessor;
use BaBeuloula\CdnPhp\Security\SsrfValidator;
use BaBeuloula\CdnPhp\Security\UrlSigner;
use BaBeuloula\CdnPhp\Storage\Storage;
use Bref\Logger\StderrLogger as BrefLogger;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem as LeagueFilesystem;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\Visibility;
use Psr\Log\LoggerInterface;

final class Container
{
    private const string KEY_ALLOWED_DOMAINS = 'allowed_domains';
    private const string KEY_DOMAINS_ALIASES = 'domains_aliases';
    private const string KEY_STORAGE_DRIVER = 'storage_driver';
    private const string KEY_STORAGE_PATH = 'storage_path';
    private const string KEY_CACHE_TTL = 'cache_ttl';
    private const string KEY_AVIF_COMPRESSION = 'avif_compression';
    private const string KEY_JPEG_COMPRESSION = 'jpeg_compression';
    private const string KEY_WEBP_COMPRESSION = 'webp_compression';
    private const string KEY_AVIF_ENABLED = 'avif_enabled';
    private const string KEY_WEBP_ENABLED = 'webp_enabled';
    private const string KEY_FETCH_TIMEOUT = 'fetch_timeout';
    private const string KEY_FETCH_MAX_SIZE = 'fetch_max_size';
    private const string KEY_FETCH_ALLOW_REDIRECTS = 'fetch_allow_redirects';
    private const string KEY_FORCE_TOKEN = 'force_token';

    /** @var array<string, mixed> */
    private array $container = [];

    public function boot(): void
    {
        $this->bootDomains();
        $this->bootLogger();
        $this->bootHttpFetcher();
        $this->bootStorage();
        $this->bootImageProcessor();
        $this->bootStaticAssetProcessor();
        $this->bootCache();

        $this->add(self::KEY_FORCE_TOKEN, $this->getEnv('FORCE_TOKEN') ?? '');

        $signatureSecret = $this->getEnv('SIGNATURE_SECRET') ?? '';
        $urlSigner = ('' !== $signatureSecret) ? new UrlSigner($signatureSecret) : null;

        $this->add(
            Cdn::class,
            new Cdn(
                $this->get(self::KEY_ALLOWED_DOMAINS),
                $this->get(self::KEY_DOMAINS_ALIASES),
                $this->get(Storage::class),
                $this->get(ImageProcessor::class),
                $this->get(StaticAssetProcessor::class),
                $this->get(Cache::class),
                $this->get(LoggerInterface::class),
                $this->get(self::KEY_FORCE_TOKEN),
                $urlSigner,
                $this->getEnv('APP_VERSION') ?? '',
                $this->get(self::KEY_AVIF_ENABLED),
                $this->get(self::KEY_WEBP_ENABLED),
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

    private function getEnv(string $key, ?string $default = null): ?string
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
                $this->getEnv('LOG_LEVEL') ?? 'debug',
                $this->getEnv('LOG_STREAM') ?? 'php://stderr',
            ),
        );
    }

    private function bootDomains(): void
    {
        $domainsAliases = [];
        foreach (explode(',', $this->getEnv('DOMAINS_ALIASES') ?? '') as $domain) {
            $domain = trim($domain);
            if ('' === $domain) {
                continue;
            }

            $parts = explode('=', $domain, 2);
            if (2 !== \count($parts)) {
                throw new \InvalidArgumentException("Domain alias '{$domain}' must use the format 'alias=domain'.");
            }

            $domainsAliases[trim($parts[1])] = trim($parts[0]);
        }
        $this->add(self::KEY_DOMAINS_ALIASES, $domainsAliases);

        $this->add(self::KEY_ALLOWED_DOMAINS, explode(',', $this->getEnv('ALLOWED_DOMAINS') ?? ''));
    }

    private function bootHttpFetcher(): void
    {
        $this->add(self::KEY_FETCH_TIMEOUT, (int) ($this->getEnv('FETCH_TIMEOUT') ?? '10'));
        $this->add(self::KEY_FETCH_MAX_SIZE, (int) ($this->getEnv('FETCH_MAX_SIZE') ?? '52428800'));
        $this->add(
            self::KEY_FETCH_ALLOW_REDIRECTS,
            true === filter_var($this->getEnv('FETCH_ALLOW_REDIRECTS') ?? '0', FILTER_VALIDATE_BOOLEAN),
        );
        $this->add(SsrfValidator::class, new SsrfValidator());
        $this->add(
            HttpFetcher::class,
            new HttpFetcher(
                $this->get(self::KEY_FETCH_TIMEOUT),
                $this->get(self::KEY_FETCH_MAX_SIZE),
                $this->get(self::KEY_FETCH_ALLOW_REDIRECTS),
                $this->get(SsrfValidator::class),
            ),
        );
    }

    private function bootStorage(): void
    {
        $this->add(self::KEY_STORAGE_DRIVER, $this->getEnv('STORAGE_DRIVER'));
        $this->add(self::KEY_STORAGE_PATH, $this->getEnv('STORAGE_PATH'));
        switch ($this->get(self::KEY_STORAGE_DRIVER)) {
            case 's3':
                $awsClient = new S3Client(
                    [
                        'version' => $this->getEnv('S3_VERSION', 'latest'),
                        'region' => $this->getEnv('S3_REGION'),
                        'endpoint' => $this->getEnv('S3_ENDPOINT'),
                        'use_path_style_endpoint' => 1 === ((int) $this->getEnv('S3_PATH_STYLE_ENDPOINT', '1')),
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
                        bucket: $this->getEnv('S3_BUCKET') ?? '',
                        visibility: new PortableVisibilityConverter(Visibility::PRIVATE),
                    ),
                );
                break;

            case 'local':
                $this->add(
                    FilesystemAdapter::class,
                    new LocalFilesystemAdapter(
                        $this->get(self::KEY_STORAGE_PATH),
                    ),
                );
                break;

            default:
                $driver = $this->get(self::KEY_STORAGE_DRIVER);
                throw new \InvalidArgumentException("Unsupported storage driver '{$driver}'.");
        }

        $this->add(
            UrlFilesystemAdapter::class,
            new UrlFilesystemAdapter(
                $this->get(HttpFetcher::class),
            ),
        );
        $this->add(LeagueFilesystem::class, new LeagueFilesystem($this->get(FilesystemAdapter::class)));
        $this->add(
            Storage::class,
            new Storage(
                $this->get(LeagueFilesystem::class),
                $this->get(HttpFetcher::class),
                $this->get(LoggerInterface::class),
            ),
        );
    }

    private function bootCache(): void
    {
        $this->add(self::KEY_CACHE_TTL, (int) $this->getEnv('CACHE_TTL'));
        $this->add(
            Cache::class,
            new Cache(
                $this->get(Storage::class),
                $this->get(self::KEY_CACHE_TTL),
            ),
        );
    }

    private function bootImageProcessor(): void
    {
        $this->add(self::KEY_AVIF_COMPRESSION, (int) ($this->getEnv('AVIF_COMPRESSION') ?? '85'));
        $this->add(self::KEY_JPEG_COMPRESSION, (int) ($this->getEnv('JPEG_COMPRESSION') ?? '75'));
        $this->add(self::KEY_WEBP_COMPRESSION, (int) ($this->getEnv('WEBP_COMPRESSION') ?? '75'));

        $this->add(
            self::KEY_AVIF_ENABLED,
            filter_var($this->getEnv('AVIF_ENABLED') ?? 'true', FILTER_VALIDATE_BOOLEAN),
        );
        $this->add(
            self::KEY_WEBP_ENABLED,
            filter_var($this->getEnv('WEBP_ENABLED') ?? 'true', FILTER_VALIDATE_BOOLEAN),
        );

        $this->add(
            ImageProcessor::class,
            new ImageProcessor(
                $this->get(FilesystemAdapter::class),
                $this->get(UrlFilesystemAdapter::class),
                $this->get(LoggerInterface::class),
                $this->get(self::KEY_JPEG_COMPRESSION),
                $this->get(self::KEY_AVIF_COMPRESSION),
                $this->get(self::KEY_WEBP_COMPRESSION),
            ),
        );
    }

    private function bootStaticAssetProcessor(): void
    {
        $this->add(
            StaticAssetProcessor::class,
            new StaticAssetProcessor(
                $this->get(FilesystemAdapter::class),
                $this->get(LoggerInterface::class),
            ),
        );
    }
}
