<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Application\Application;
use EzPhp\Config\Config;
use EzPhp\Contracts\ExceptionHandlerInterface;
use EzPhp\I18n\Translator;
use EzPhp\Middleware\DebugToolbarMiddleware;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class ExceptionHandlerServiceProvider
 *
 * @internal
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

    /**
     * @return void
     */
    public function boot(): void
    {
        /** @var Application $app */
        $app = $this->app;

        // Read APP_DEBUG directly from the environment instead of resolving Config,
        // to keep Config lazy — test code sets env vars after bootstrap() and before
        // the first make() call; eager Config resolution would freeze the values too early.
        $debug = (bool) filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);

        if ($debug) {
            $app->middleware(DebugToolbarMiddleware::class);
        }
    }
}
