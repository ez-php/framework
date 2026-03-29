<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use EzPhp\ServiceProvider\ServiceProvider;
use ReflectionException;

/**
 * Class RouterServiceProvider
 *
 * @internal
 * @package EzPhp\Routing
 */
final class RouterServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Router::class, fn () => new Router($this->app));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function boot(): void
    {
        $router = $this->app->make(Router::class);

        $cachePath = $this->basePath('bootstrap/cache/routes.php');

        if (file_exists($cachePath)) {
            $this->loadFromCache($router, $cachePath);
            return;
        }

        $routesPath = $this->basePath('routes/web.php');

        if (!file_exists($routesPath)) {
            return;
        }

        (static function (Router $router) use ($routesPath): void {
            require $routesPath;
        })($router);
    }

    /**
     * Reconstruct routes from the serialized cache file.
     *
     * Each entry in the cache was produced by Router::toCache() and contains
     * only array-handler routes. Closures are never cached.
     *
     * @param Router $router
     * @param string $cachePath
     *
     * @return void
     */
    private function loadFromCache(Router $router, string $cachePath): void
    {
        /** @var list<array{method: string, path: string, name: string|null, handler: array{0: class-string, 1: string}, middleware: array<int, class-string<\EzPhp\Middleware\MiddlewareInterface>>, constraints: array<string, string>, csrfExempt: bool}> $data */
        $data = require $cachePath;

        foreach ($data as $entry) {
            $route = $router->add($entry['method'], $entry['path'], $entry['handler']);

            if ($entry['name'] !== null) {
                $route->name($entry['name']);
            }

            foreach ($entry['middleware'] as $middleware) {
                $route->middleware($middleware);
            }

            foreach ($entry['constraints'] as $param => $pattern) {
                $route->where($param, $pattern);
            }

            if ($entry['csrfExempt']) {
                $route->withoutCsrf();
            }
        }
    }
}
