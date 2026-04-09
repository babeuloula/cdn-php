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

use BaBeuloula\CdnPhp\Exception\ExpiredUrlException;
use BaBeuloula\CdnPhp\Exception\InvalidSignatureException;

final class UrlSigner
{
    public function __construct(private readonly string $secret)
    {
    }

    /**
     * @throws ExpiredUrlException
     * @throws InvalidSignatureException
     */
    public function verify(string $imageUrl, int $expires, string $sig): void
    {
        if (0 === $expires || '' === $sig) {
            throw new InvalidSignatureException();
        }

        if ($expires < time()) {
            throw new ExpiredUrlException();
        }

        $expected = hash_hmac('sha256', $imageUrl . ':' . $expires, $this->secret);

        if (false === hash_equals($expected, $sig)) {
            throw new InvalidSignatureException();
        }
    }
}
