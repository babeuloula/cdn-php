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

class InvalidUriException extends \InvalidArgumentException
{
    public function __construct(string $uri)
    {
        parent::__construct('Invalid URI: ' . $uri, code: Response::HTTP_BAD_REQUEST);
    }
}
