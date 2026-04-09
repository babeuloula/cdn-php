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

use BaBeuloula\CdnPhp\Exception\ExpiredUrlException;
use BaBeuloula\CdnPhp\Exception\InvalidSignatureException;
use BaBeuloula\CdnPhp\Security\UrlSigner;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UrlSignerTest extends TestCase
{
    private const string SECRET = 'test-secret';
    private const string IMAGE_URL = 'https://example.com/image.jpg';
    private UrlSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new UrlSigner(self::SECRET);
    }

    #[Test]
    public function acceptsValidSignature(): void
    {
        $expires = time() + 3600;
        $sig = hash_hmac('sha256', self::IMAGE_URL . ':' . $expires, self::SECRET);

        $this->expectNotToPerformAssertions();
        $this->signer->verify(self::IMAGE_URL, $expires, $sig);
    }

    #[Test]
    public function rejectsInvalidSignature(): void
    {
        static::expectException(InvalidSignatureException::class);

        $expires = time() + 3600;
        $this->signer->verify(self::IMAGE_URL, $expires, 'wrong-sig');
    }

    #[Test]
    public function rejectsMissingSignature(): void
    {
        static::expectException(InvalidSignatureException::class);

        $this->signer->verify(self::IMAGE_URL, 0, '');
    }

    #[Test]
    public function rejectsExpiredUrl(): void
    {
        static::expectException(ExpiredUrlException::class);

        $expires = time() - 1;
        $sig = hash_hmac('sha256', self::IMAGE_URL . ':' . $expires, self::SECRET);

        $this->signer->verify(self::IMAGE_URL, $expires, $sig);
    }
}
