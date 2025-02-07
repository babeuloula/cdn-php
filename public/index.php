<?php

/**
 * @author      BaBeuloula <info@babeuloula.fr>
 * @copyright   Copyright (c) BaBeuloula
 * @license     MIT
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require '../vendor/autoload.php';

use BaBeuloula\CdnPhp\Cdn;
use BaBeuloula\CdnPhp\ContainerConfig;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;

$dotenv = new Dotenv();
$dotenv->usePutenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

if (true === filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)) {
    Debug::enable();
}

ErrorHandler::call(
    static function () {
        $container = new ContainerConfig();

        $request = Request::createFromGlobals();

        /** @var Cdn $cdn */
        $cdn = $container[Cdn::class];
        $response = $cdn->handleRequest($request);

        $response->send();
    }
);
