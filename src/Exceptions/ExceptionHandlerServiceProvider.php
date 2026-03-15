<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Application\Application;
use EzPhp\Config\Config;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class ExceptionHandlerServiceProvider
 *
 * @package EzPhp\Exceptions
 */
final class ExceptionHandlerServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(ExceptionHandler::class, function (Application $app): DefaultExceptionHandler {
            $debug = (bool) $app->make(Config::class)->get('app.debug', false);
            $templatePath = $app->basePath('resources/errors');

            return new DefaultExceptionHandler($debug, $templatePath);
        });
    }
}
