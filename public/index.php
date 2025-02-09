<?php

/**
 * @author      BaBeuloula <info@babeuloula.fr>
 * @copyright   Copyright (c) BaBeuloula
 * @license     MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once \dirname(__DIR__) . '/vendor/autoload_runtime.php';

use BaBeuloula\CdnPhp\Cdn;
use BaBeuloula\CdnPhp\ContainerConfig;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

return static function (Request $request): Response {
    $container = new ContainerConfig();
    /** @var Cdn $cdn */
    $cdn = $container[Cdn::class];

    // phpcs:ignore
    if (true === filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN)) {
        Debug::enable();

        return ErrorHandler::call(static fn () => $cdn->handleRequest($request));
    }

    return $cdn->handleRequest($request);
};
