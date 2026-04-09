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

class FileTooLargeException extends CdnException
{
    public function __construct(string $url, int $maxBytes)
    {
        parent::__construct(
            sprintf('File at %s exceeds the maximum allowed size of %d bytes.', $url, $maxBytes),
            code: Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
        );
    }
}
