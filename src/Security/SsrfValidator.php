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

namespace BaBeuloula\CdnPhp\Security;

use BaBeuloula\CdnPhp\Exception\SsrfAttemptException;

final class SsrfValidator
{
    /**
     * Private/reserved IPv4 CIDR ranges that must never be reached.
     *
     * @var array<array{string, int}>
     */
    private const array BLOCKED_CIDRS = [
        ['0.0.0.0', 8],           // Unspecified
        ['10.0.0.0', 8],          // RFC 1918 private
        ['100.64.0.0', 10],       // Shared address space
        ['127.0.0.0', 8],         // Loopback
        ['169.254.0.0', 16],      // Link-local / AWS EC2 metadata
        ['172.16.0.0', 12],       // RFC 1918 private
        ['192.168.0.0', 16],      // RFC 1918 private
        ['198.18.0.0', 15],       // Benchmarking
        ['240.0.0.0', 4],         // Reserved
    ];

    /** @throws SsrfAttemptException */
    public function assertSafe(string $url): void
    {
        $hostname = (string) parse_url($url, PHP_URL_HOST);

        if ('' === $hostname) {
            throw new SsrfAttemptException($url);
        }

        // If the hostname is already an IP, validate it directly without DNS resolution
        if (false !== filter_var($hostname, FILTER_VALIDATE_IP)) {
            if (true === $this->isPrivateIp($hostname)) {
                throw new SsrfAttemptException($url);
            }

            return;
        }

        $ipAddress = gethostbyname($hostname);

        if ($ipAddress === $hostname) {
            // gethostbyname returns the hostname unchanged when resolution fails
            throw new SsrfAttemptException($url);
        }

        if (true === $this->isPrivateIp($ipAddress)) {
            throw new SsrfAttemptException($url);
        }
    }

    private function isPrivateIp(string $ipAddress): bool
    {
        $ipLong = ip2long($ipAddress);

        if (false === $ipLong) {
            return true;
        }

        foreach (self::BLOCKED_CIDRS as [$network, $prefix]) {
            $networkLong = ip2long($network);
            $mask = ~((1 << (32 - $prefix)) - 1);

            if (false !== $networkLong && ($ipLong & $mask) === ($networkLong & $mask)) {
                return true;
            }
        }

        return false;
    }
}
