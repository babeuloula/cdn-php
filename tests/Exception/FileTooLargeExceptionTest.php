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

namespace BaBeuloula\CdnPhp\Tests\Exception;

use BaBeuloula\CdnPhp\Exception\FileTooLargeException;
use BaBeuloula\CdnPhp\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

class FileTooLargeExceptionTest extends TestCase
{
    #[Test]
    public function canCreateException(): void
    {
        $exception = new FileTooLargeException('https://example.com/big.jpg', 1024);

        static::assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $exception->getCode());
        static::assertStringContainsString('https://example.com/big.jpg', $exception->getMessage());
        static::assertStringContainsString('1024', $exception->getMessage());
    }
}
