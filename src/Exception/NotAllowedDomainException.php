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

class NotAllowedDomainException extends \InvalidArgumentException
{
    public function __construct(string $domain)
    {
        parent::__construct('The domain "' . $domain . '" is not allowed.', code: Response::HTTP_FORBIDDEN);
    }
}
