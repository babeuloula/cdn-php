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
use BaBeuloula\CdnPhp\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__ . '/../.env');

$container = new Container();
$container->boot();
/** @var Cdn $cdn */
$cdn = $container->get(Cdn::class);
/** @var LoggerInterface $logger */
$logger = $container->get(LoggerInterface::class);

return static function (Request $request) use ($cdn, $logger): Response {
    try {
        return $cdn->handleRequest($request);
    } catch (\Throwable $exception) {
        $logger->error(
            'An error occurred: {message}',
            [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]
        );

        // phpcs:ignore
        if (true === filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN)) {
            Debug::enable();
        }

        return ErrorHandler::call(static fn () => throw $exception);
    }
};
