<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Container\Container;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Routing\Route;

/**
 * Class MiddlewareHandler
 *
 * @internal
 * @package EzPhp\Middleware
 */
final class MiddlewareHandler
{
    /**
     * @var array<int, class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    /**
     * @var list<MiddlewareInterface>
     */
    private array $resolved = [];

    /**
     * @var list<class-string>
     */
    private array $priority = [];

    /**
     * @var array<string, class-string<MiddlewareInterface>>
     */
    private array $aliases = [];

    /**
     * @var array<string, list<class-string<MiddlewareInterface>>>
     */
    private array $groups = [];

    /**
     * MiddlewareHandler Constructor
     *
     * @param Container $container
     */
    public function __construct(private readonly Container $container)
    {
        //
    }

    /**
     * @param class-string<MiddlewareInterface> $middleware
     *
     * @return void
     */
    public function add(string $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Register middleware aliases. An alias is a short string key (e.g. 'auth')
     * that maps to a fully-qualified middleware class. Aliases are resolved in
     * buildPipeline() before the class is made from the container.
     *
     * @param array<string, class-string<MiddlewareInterface>> $aliases
     *
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Register named middleware groups. A group maps a short name (e.g. 'api') to a
     * list of middleware class-strings or aliases. Groups are expanded before the
     * pipeline is built so that route middleware can reference group names.
     *
     * @param array<string, list<class-string<MiddlewareInterface>>> $groups
     *
     * @return void
     */
    public function setGroups(array $groups): void
    {
        $this->groups = $groups;
    }

    /**
     * Set the middleware priority order. Middleware appearing earlier in the
     * list will run first, regardless of the order they were added. Middleware
     * not in the priority list retains its original relative order and runs
     * after all prioritized middleware.
     *
     * @param list<class-string> $priority
     *
     * @return void
     */
    public function setPriority(array $priority): void
    {
        $this->priority = array_values(array_unique($priority));
    }

    /**
     * Run the full pipeline (global + route middleware) then the route handler.
     *
     * @param Route   $route
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Route $route, Request $request): Response
    {
        $stack = $this->sortByPriority(
            $this->expandGroups(array_merge($this->middleware, $route->getMiddleware()))
        );
        $this->resolved = [];

        return $this->buildPipeline(
            fn (Request $r): Response => $route->run($r),
            $stack,
            0,
        )($request);
    }

    /**
     * Run only the global middleware pipeline, then call $terminal.
     * Used by Application::handle() so that global middleware can intercept
     * requests (e.g. CORS preflight) before routing takes place.
     *
     * @param Request                   $request
     * @param callable(Request):Response $terminal Called when all global middleware have passed.
     *
     * @return Response
     */
    public function dispatch(Request $request, callable $terminal): Response
    {
        $this->resolved = [];

        return $this->buildPipeline(
            $terminal,
            $this->sortByPriority($this->expandGroups($this->middleware)),
            0,
        )($request);
    }

    /**
     * Run route-level middleware only, then the route handler.
     * Called from inside the terminal passed to dispatch().
     *
     * @param Route   $route
     * @param Request $request
     *
     * @return Response
     */
    public function runRoute(Route $route, Request $request): Response
    {
        return $this->buildPipeline(
            fn (Request $r): Response => $route->run($r),
            $this->expandGroups($route->getMiddleware()),
            0,
        )($request);
    }

    /**
     * Call terminate() on any middleware that implements TerminableMiddleware.
     * Should be called after the response has been sent to the client.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        foreach ($this->resolved as $middleware) {
            if ($middleware instanceof TerminableMiddleware) {
                $middleware->terminate($request, $response);
            }
        }
    }

    /**
     * Expand group names in a middleware stack to their constituent entries.
     * Entries that are not registered group names are passed through unchanged.
     *
     * @param array<int, class-string<MiddlewareInterface>> $stack
     *
     * @return array<int, class-string<MiddlewareInterface>>
     */
    private function expandGroups(array $stack): array
    {
        $result = [];

        foreach ($stack as $entry) {
            if (isset($this->groups[$entry])) {
                foreach ($this->groups[$entry] as $class) {
                    $result[] = $class;
                }
            } else {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * Sort a middleware stack according to the configured priority list.
     * Middleware in the priority list comes first (in priority list order).
     * Unprioritized middleware follows in its original order.
     *
     * @param array<int, class-string<MiddlewareInterface>> $stack
     *
     * @return array<int, class-string<MiddlewareInterface>>
     */
    private function sortByPriority(array $stack): array
    {
        if ($this->priority === []) {
            return $stack;
        }

        $prioritized = [];
        $rest = [];

        foreach ($stack as $class) {
            if (in_array($class, $this->priority, true)) {
                $prioritized[] = $class;
            } else {
                $rest[] = $class;
            }
        }

        usort($prioritized, function (string $a, string $b): int {
            $posA = array_search($a, $this->priority, true);
            $posB = array_search($b, $this->priority, true);
            return ($posA === false ? PHP_INT_MAX : $posA) <=> ($posB === false ? PHP_INT_MAX : $posB);
        });

        return array_merge($prioritized, $rest);
    }

    /**
     * @param callable(Request):Response              $terminal
     * @param array<int, class-string<MiddlewareInterface>> $stack
     * @param int                                          $index
     *
     * @return callable(Request): Response
     */
    private function buildPipeline(callable $terminal, array $stack, int $index): callable
    {
        if ($index >= count($stack)) {
            return $terminal;
        }

        return function (Request $request) use ($terminal, $stack, $index): Response {
            $class = $this->aliases[$stack[$index]] ?? $stack[$index];
            $middleware = $this->container->make($class);
            $this->resolved[] = $middleware;
            return $middleware->handle($request, $this->buildPipeline($terminal, $stack, $index + 1));
        };
    }
}
