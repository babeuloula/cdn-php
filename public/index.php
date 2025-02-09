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

if (true === filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    Debug::enable();
}

return static function (Request $request): Response {
    return ErrorHandler::call(
        static function () use ($request) {
            $container = new ContainerConfig();

            /** @var Cdn $cdn */
            $cdn = $container[Cdn::class];

            return $cdn->handleRequest($request);
        }
    );
};
