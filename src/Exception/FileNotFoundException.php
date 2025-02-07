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

class FileNotFoundException extends \Exception
{
    public function __construct(string $file, \Throwable $previous)
    {
        parent::__construct("File {$file} not found", code: Response::HTTP_NOT_FOUND, previous: $previous);
    }
}
