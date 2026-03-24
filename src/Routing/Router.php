<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use Closure;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Exceptions\NotFoundException;
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
     * Routes partitioned by HTTP method for O(1) method lookup.
     *
     * @var array<string, list<Route>>
     */
    private array $routes = [];

    private ?Route $fallbackRoute = null;

    private string $groupPrefix = '';

    /**
     * Model bindings: param name → resolver closure.
     *
     * @var array<string, Closure(string): ?object>
     */
    private array $modelBindings = [];

    /**
     * @var list<class-string<MiddlewareInterface>>
     */
    private array $groupMiddleware = [];

    /**
     * Router Constructor
     *
     * @param ContainerInterface|null $container Optional container used to resolve
     *                                           [Controller::class, 'method'] array handlers.
     */
    public function __construct(private readonly ?ContainerInterface $container = null)
    {
    }

    /**
     * @param string                               $path
     * @param callable|array{class-string, string} $handler
     *
     * @return Route
     */
    public function get(string $path, callable|array $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    /**
     * @param string                               $path
     * @param callable|array{class-string, string} $handler
     *
     * @return Route
     */
    public function post(string $path, callable|array $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * @param string                               $path
     * @param callable|array{class-string, string} $handler
     *
     * @return Route
     */
    public function put(string $path, callable|array $handler): Route
    {
        return $this->add('PUT', $path, $handler);
    }

    /**
     * @param string                               $path
     * @param callable|array{class-string, string} $handler
     *
     * @return Route
     */
    public function patch(string $path, callable|array $handler): Route
    {
        return $this->add('PATCH', $path, $handler);
    }

    /**
     * @param string                               $path
     * @param callable|array{class-string, string} $handler
     *
     * @return Route
     */
    public function delete(string $path, callable|array $handler): Route
    {
        return $this->add('DELETE', $path, $handler);
    }

    /**
     * Register a route for any HTTP method.
     *
     * The handler may be:
     * - any PHP callable (closure, function name, invokable)
     * - a `[Controller::class, 'method']` tuple — resolved from the container at dispatch time
     *
     * @param string                               $method  HTTP method (will be uppercased).
     * @param string                               $path    Route URI path.
     * @param callable|array{class-string, string} $handler Route handler.
     *
     * @return Route
     */
    public function add(string $method, string $path, callable|array $handler): Route
    {
        $fullPath = $this->groupPrefix . $path;
        $method = strtoupper($method);

        foreach ($this->routes[$method] ?? [] as $existing) {
            if ($existing->getPath() === $fullPath) {
                throw new InvalidArgumentException(
                    "Duplicate route: $method $fullPath is already registered."
                );
            }
        }

        $callable = is_array($handler)
            ? $this->resolveControllerAction($handler)
            : $handler;

        $route = new Route($method, $fullPath, $callable);

        foreach ($this->groupMiddleware as $middleware) {
            $route->middleware($middleware);
        }

        $this->routes[$method][] = $route;

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

        try {
            $callback($this);
        } finally {
            $this->groupPrefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    /**
     * Register the seven standard RESTful routes for a resource controller.
     *
     * Routes registered (prefix = /{resource}):
     *
     * | Method | URI                   | Action  | Name                  |
     * |--------|-----------------------|---------|-----------------------|
     * | GET    | /{resource}           | index   | {resource}.index      |
     * | GET    | /{resource}/create    | create  | {resource}.create     |
     * | POST   | /{resource}           | store   | {resource}.store      |
     * | GET    | /{resource}/{id}      | show    | {resource}.show       |
     * | GET    | /{resource}/{id}/edit | edit    | {resource}.edit       |
     * | PUT    | /{resource}/{id}      | update  | {resource}.update     |
     * | DELETE | /{resource}/{id}      | destroy | {resource}.destroy    |
     *
     * The controller instance is captured by the route closures, so it is
     * shared across all requests — keep resource controllers stateless.
     *
     * Use `$only` to register a subset of actions, or `$except` to skip specific ones.
     * When both are provided, `$only` is applied first, then `$except` filters the result.
     *
     * @param string                      $resource   Plural resource name, e.g. 'posts'.
     * @param ResourceControllerInterface $controller Resolved controller instance.
     * @param list<string>                $only       Restrict to these action names.
     * @param list<string>                $except     Exclude these action names.
     *
     * @return void
     */
    public function resource(
        string $resource,
        ResourceControllerInterface $controller,
        array $only = [],
        array $except = []
    ): void {
        $base = '/' . ltrim($resource, '/');

        /** @var array<string, Closure(): Route> $actions */
        $actions = [
            'index' => fn (): Route => $this->get($base, fn (Request $r): Response|string => $controller->index($r))->name("$resource.index"),
            'create' => fn (): Route => $this->get($base . '/create', fn (Request $r): Response|string => $controller->create($r))->name("$resource.create"),
            'store' => fn (): Route => $this->post($base, fn (Request $r): Response|string => $controller->store($r))->name("$resource.store"),
            'show' => fn (): Route => $this->get($base . '/{id}', fn (Request $r): Response|string => $controller->show($r))->name("$resource.show"),
            'edit' => fn (): Route => $this->get($base . '/{id}/edit', fn (Request $r): Response|string => $controller->edit($r))->name("$resource.edit"),
            'update' => fn (): Route => $this->put($base . '/{id}', fn (Request $r): Response|string => $controller->update($r))->name("$resource.update"),
            'destroy' => fn (): Route => $this->delete($base . '/{id}', fn (Request $r): Response|string => $controller->destroy($r))->name("$resource.destroy"),
        ];

        $toRegister = $only !== [] ? array_intersect_key($actions, array_flip($only)) : $actions;

        if ($except !== []) {
            $toRegister = array_diff_key($toRegister, array_flip($except));
        }

        foreach ($toRegister as $register) {
            $register();
        }
    }

    /**
     * Register a fallback route that matches any method and any path.
     *
     * The fallback is tried last, after all other routes fail to match.
     * It does not participate in duplicate detection.
     *
     * @param callable $handler
     *
     * @return Route
     */
    public function fallback(callable $handler): Route
    {
        $route = new Route('ANY', '/*', $handler);
        $this->fallbackRoute = $route;
        return $route;
    }

    /**
     * Register a model binding for a named route parameter.
     *
     * When a route with a matching parameter is resolved, the Router replaces
     * the raw string value with the model instance returned by the resolver.
     * A null return from the resolver throws a NotFoundException (404).
     *
     * Default resolver — calls `$modelClass::find($id)` statically:
     *
     *   $router->model('user', User::class);
     *
     * Custom resolver:
     *
     *   $router->model('user', User::class, fn (string $id): ?User => User::withTrashed()->find($id));
     *
     * @param string                    $param     Route parameter name (without braces).
     * @param class-string              $modelClass Model class used for the default find() resolver.
     * @param Closure(string): ?object|null $resolver   Custom resolver; null uses the default.
     *
     * @return void
     */
    public function model(string $param, string $modelClass, ?Closure $resolver = null): void
    {
        if ($resolver !== null) {
            $this->modelBindings[$param] = $resolver;
            return;
        }

        $this->modelBindings[$param] = static function (string $id) use ($modelClass): ?object {
            /** @var callable(string): ?object $finder */
            $finder = [$modelClass, 'find'];
            return $finder($id);
        };
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
        foreach ($this->routes as $methodRoutes) {
            foreach ($methodRoutes as $route) {
                if ($route->getName() === $name) {
                    return $route->generateUrl($params);
                }
            }
        }

        throw new RouteException("Named route '$name' not found");
    }

    /**
     * @internal Called by MiddlewareHandler during request dispatch; not part of the public router API.
     *
     * @param Request $request
     *
     * @return Route
     * @throws RouteException
     */
    public function retrieveRoute(Request $request): Route
    {
        $matched = $this->matchRoute($request);

        if ($matched !== null) {
            return $this->applyModelBindings($matched);
        }

        if ($this->fallbackRoute !== null) {
            return $this->fallbackRoute;
        }

        throw new RouteException();
    }

    /**
     * Check whether the request matches a route that is exempt from CSRF verification.
     *
     * This is a lightweight alternative to retrieveRoute() used by CsrfMiddleware.
     * It matches the route pattern but intentionally skips model binding to avoid
     * triggering database queries for what is only an exemption check.
     *
     * Returns false for unmatched requests (they will produce a 404 in the main
     * dispatch; the CSRF check is irrelevant).
     *
     * @internal Called by CsrfMiddleware; not part of the public router API.
     *
     * @param Request $request
     *
     * @return bool
     */
    public function isCsrfExemptRoute(Request $request): bool
    {
        $matched = $this->matchRoute($request);

        return $matched !== null && $matched->isCsrfExempt();
    }

    /**
     * Apply HTTP method override and find the first matching route.
     *
     * Handles the `_method` POST field override (for HTML form PUT/PATCH/DELETE),
     * then iterates over registered routes and returns the first match, or null.
     *
     * @param Request $request
     *
     * @return Route|null
     */
    private function matchRoute(Request $request): ?Route
    {
        // HTTP method override for HTML forms (POST with _method field)
        if ($request->method() === 'POST') {
            $override = $request->input('_method');
            if (is_string($override) && $override !== '') {
                $request = $request->withMethod(strtoupper($override));
            }
        }

        foreach ($this->routes[$request->method()] ?? [] as $route) {
            if (($matched = $route->matches($request)) !== null) {
                return $matched;
            }
        }

        return null;
    }

    /**
     * Apply registered model bindings to the matched route's raw string params.
     *
     * For each param that has a model binding, call the resolver with the raw
     * string value. A null return indicates the model was not found and a
     * NotFoundException (404) is thrown. Unbound params are left unchanged.
     *
     * @param Route $route
     *
     * @return Route
     */
    private function applyModelBindings(Route $route): Route
    {
        if ($this->modelBindings === []) {
            return $route;
        }

        $resolved = $route->getParams();
        $changed = false;

        foreach ($this->modelBindings as $param => $resolver) {
            if (!array_key_exists($param, $resolved)) {
                continue;
            }

            $raw = $resolved[$param];

            if (!is_string($raw)) {
                continue;
            }

            $model = $resolver($raw);

            if ($model === null) {
                throw new NotFoundException("Model not found for route parameter '$param'.");
            }

            $resolved[$param] = $model;
            $changed = true;
        }

        return $changed ? $route->withResolvedParams($resolved) : $route;
    }

    /**
     * Wrap a [Controller::class, 'method'] tuple in a lazy closure that resolves
     * the controller from the container at dispatch time.
     *
     * @param array<mixed> $handler
     *
     * @return Closure
     */
    private function resolveControllerAction(array $handler): Closure
    {
        if ($this->container === null) {
            throw new InvalidArgumentException(
                'Container required for [Controller::class, method] dispatch. ' .
                'Ensure RouterServiceProvider is registered or inject a ContainerInterface into Router.'
            );
        }

        $class = $handler[0] ?? null;
        $action = $handler[1] ?? null;

        if (!is_string($class) || $class === '') {
            throw new InvalidArgumentException(
                'Array handler[0] must be a non-empty class-string.'
            );
        }

        if (!is_string($action) || $action === '') {
            throw new InvalidArgumentException(
                'Array handler[1] must be a non-empty method name.'
            );
        }

        /** @var class-string $class */
        $container = $this->container;

        return static function (Request $r) use ($container, $class, $action): mixed {
            $controller = $container->make($class);
            /** @var callable(Request): mixed $callable */
            $callable = [$controller, $action];
            return $callable($r);
        };
    }
}
