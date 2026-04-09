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

namespace BaBeuloula\CdnPhp\Tests\Security;

use BaBeuloula\CdnPhp\Exception\SsrfAttemptException;
use BaBeuloula\CdnPhp\Security\SsrfValidator;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class SsrfValidatorTest extends TestCase
{
    private SsrfValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new SsrfValidator();
    }

    #[DataProvider('privateIpProvider')]
    #[Test]
    public function blocksPrivateIp(string $ip): void
    {
        static::expectException(SsrfAttemptException::class);

        $this->validator->assertSafe("http://{$ip}/file.jpg");
    }

    public static function privateIpProvider(): \Generator
    {
        yield 'loopback' => ['127.0.0.1'];
        yield 'loopback-other' => ['127.0.0.2'];
        yield 'link-local (AWS metadata)' => ['169.254.169.254'];
        yield 'private-10' => ['10.0.0.1'];
        yield 'private-172' => ['172.16.0.1'];
        yield 'private-192' => ['192.168.1.1'];
        yield 'unspecified' => ['0.0.0.0'];
    }

    #[Test]
    public function allowsPublicIp(): void
    {
        // 93.184.216.34 is example.com's public IP - must not throw
        $this->expectNotToPerformAssertions();
        $this->validator->assertSafe('https://93.184.216.34/image.jpg');
    }

    #[Test]
    public function blocksEmptyHostname(): void
    {
        static::expectException(SsrfAttemptException::class);

        // http:///path has no host - parse_url returns ''
        $this->validator->assertSafe('http:///secret-file.jpg');
    }

    #[Test]
    public function blocksUnresolvableDomain(): void
    {
        static::expectException(SsrfAttemptException::class);

        // .invalid TLD is IANA-reserved and will never resolve
        $this->validator->assertSafe('http://this-host-does-not-exist.invalid/file.jpg');
    }

    #[Test]
    public function blocksIPv6Address(): void
    {
        static::expectException(SsrfAttemptException::class);

        // IPv6 addresses cannot be validated via ip2long (IPv4-only) - treated as private
        $this->validator->assertSafe('http://[::1]/file.jpg');
    }
}
