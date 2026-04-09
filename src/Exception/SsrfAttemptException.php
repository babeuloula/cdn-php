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

namespace BaBeuloula\CdnPhp\Exception;

use Symfony\Component\HttpFoundation\Response;

class SsrfAttemptException extends CdnException
{
    public function __construct(string $url)
    {
        parent::__construct("SSRF attempt blocked: {$url}", code: Response::HTTP_FORBIDDEN);
    }
}
