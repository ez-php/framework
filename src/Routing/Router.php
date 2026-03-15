<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareInterface;
use InvalidArgumentException;

/**
 * Class Router
 *
 * @package EzPhp\Routing
 */
final class Router
{
    /**
     * @var Route[]
     */
    private array $routes = [];

    private string $groupPrefix = '';

    /**
     * @var list<class-string<MiddlewareInterface>>
     */
    private array $groupMiddleware = [];

    /**
     * @param string   $path
     * @param callable $handler
     *
     * @return Route
     */
    public function get(string $path, callable $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * @param string   $path
     * @param callable $handler
     *
     * @return Route
     */
    public function post(string $path, callable $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * @param string   $path
     * @param callable $handler
     *
     * @return Route
     */
    public function put(string $path, callable $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * @param string   $path
     * @param callable $handler
     *
     * @return Route
     */
    public function delete(string $path, callable $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * @param string   $method
     * @param string   $path
     * @param callable $handler
     *
     * @return Route
     */
    public function add(string $method, string $path, callable $handler): Route
    {
        $fullPath = $this->groupPrefix . $path;
        $method = strtoupper($method);

        foreach ($this->routes as $existing) {
            if ($existing->getMethod() === $method && $existing->getPath() === $fullPath) {
                throw new InvalidArgumentException(
                    "Duplicate route: $method $fullPath is already registered."
                );
            }
        }

        $route = new Route($method, $fullPath, $handler);

        foreach ($this->groupMiddleware as $middleware) {
            $route->middleware($middleware);
        }

        $this->routes[] = $route;

        return $route;
    }

    /**
     * @param string                                      $prefix
     * @param callable                                    $callback
     * @param list<class-string<MiddlewareInterface>>     $middleware
     *
     * @return void
     */
    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Register a redirect route that issues an HTTP redirect response.
     *
     * @param string $from   URI to redirect from.
     * @param string $to     URI to redirect to.
     * @param int    $status HTTP redirect status code (default: 302).
     *
     * @return Route
     */
    public function redirect(string $from, string $to, int $status = 302): Route
    {
        return $this->add('GET', $from, static fn (): Response => (new Response('', $status))->withHeader('Location', $to));
    }

    /**
     * @param string               $name
     * @param array<string, string> $params
     *
     * @return string
     * @throws RouteException
     */
    public function route(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route->generateUrl($params);
            }
        }

        throw new RouteException("Named route '$name' not found");
    }

    /**
     * @param Request $request
     *
     * @return Route
     * @throws RouteException
     */
    public function retrieveRoute(Request $request): Route
    {
        // HTTP method override for HTML forms (POST with _method field)
        if ($request->method() === 'POST') {
            $override = $request->input('_method');
            if (is_string($override) && $override !== '') {
                $request = $request->withMethod(strtoupper($override));
            }
        }

        foreach ($this->routes as $route) {
            if (($matched = $route->matches($request)) !== null) {
                return $matched;
            }
        }

        throw new RouteException();
    }
}
