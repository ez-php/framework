<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Application\Application;
use EzPhp\Config\Config;
use EzPhp\Contracts\ExceptionHandlerInterface;
use EzPhp\I18n\Translator;
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
        $this->app->bind(ExceptionHandlerInterface::class, function (Application $app): DefaultExceptionHandler {
            $debug = (bool) $app->make(Config::class)->get('app.debug', false);
            $templatePath = $app->basePath('resources/errors');
            $translator = $app->make(Translator::class);

            return new DefaultExceptionHandler($debug, $templatePath, $translator);
        });

        $this->app->bind(ExceptionHandler::class, fn () => $this->app->make(ExceptionHandlerInterface::class));
    }
}
