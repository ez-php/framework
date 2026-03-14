<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use EzPhp\ServiceProvider\ServiceProvider;
use ReflectionException;

/**
 * Class RouterServiceProvider
 *
 * @package EzPhp\Routing
 */
final class RouterServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Router::class, fn () => new Router());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $routesPath = $this->app->basePath('routes/web.php');

        if (!file_exists($routesPath)) {
            return;
        }

        $router = $this->app->make(Router::class);

        (static function (Router $router) use ($routesPath): void {
            require $routesPath;
        })($router);
    }
}
