<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Container\Container;
use EzPhp\Contracts\MiddlewareInterface as ContractsMiddlewareInterface;
use EzPhp\Contracts\ParameterizedMiddlewareInterface;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Http\Request;
use EzPhp\Http\ResponseInterface;
use EzPhp\Routing\Route;

/**
 * Class MiddlewareHandler
 *
 * Middleware entries are strings: a class name, an alias, or a group name,
 * optionally followed by `:` and comma-separated parameters
 * (`'can:update,App\Post'`). Parameters are passed to the middleware's
 * handle() as extra string arguments; the middleware must implement
 * ParameterizedMiddlewareInterface to receive them. Entries stay plain strings,
 * so route:cache and route:list keep working unchanged.
 *
 * @internal
 * @package EzPhp\Middleware
 */
final class MiddlewareHandler
{
    /**
     * @var array<int, non-empty-string>
     */
    private array $middleware = [];

    /**
     * @var list<ContractsMiddlewareInterface>
     */
    private array $resolved = [];

    /**
     * @var list<class-string>
     */
    private array $priority = [];

    /**
     * @var array<string, class-string<ContractsMiddlewareInterface>>
     */
    private array $aliases = [];

    /**
     * @var array<string, list<non-empty-string>>
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
     * @param non-empty-string $middleware Class, alias or group name, optionally with `:param1,param2`.
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
     * @param array<string, class-string<ContractsMiddlewareInterface>> $aliases
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
     * @param array<string, list<non-empty-string>> $groups
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
     * `Application::handle()` does NOT call this method — it calls dispatch()
     * and runRoute() separately instead, because routing must happen *after*
     * global middleware runs (so middleware like CorsMiddleware can intercept a
     * request, e.g. an OPTIONS preflight, before any route is resolved), and the
     * Route required by this method's combined global+route stack isn't known
     * until inside that global-middleware pipeline. This method is kept as a
     * lower-level, independently-testable primitive for callers that already
     * have a Route in hand — see MiddlewareHandlerTest, TerminableMiddlewareTest,
     * and StreamThroughMiddlewareTest, which exercise it without a full
     * Application/Router round-trip.
     *
     * @param Route   $route
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function handle(Route $route, Request $request): ResponseInterface
    {
        $stack = $this->sortByPriority(
            $this->expandGroups(array_merge($this->middleware, $route->getMiddleware()))
        );
        $this->resolved = [];

        return $this->buildPipeline(
            fn (Request $r): ResponseInterface => $route->run($r),
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
     * @param callable(Request):ResponseInterface $terminal Called when all global middleware have passed.
     *
     * @return ResponseInterface
     */
    public function dispatch(Request $request, callable $terminal): ResponseInterface
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
     * @return ResponseInterface
     */
    public function runRoute(Route $route, Request $request): ResponseInterface
    {
        return $this->buildPipeline(
            fn (Request $r): ResponseInterface => $route->run($r),
            $this->expandGroups($route->getMiddleware()),
            0,
        )($request);
    }

    /**
     * Call terminate() on any middleware that implements TerminableMiddleware.
     * Should be called after the response has been sent to the client.
     *
     * @param Request  $request
     * @param ResponseInterface $response
     *
     * @return void
     */
    public function terminate(Request $request, ResponseInterface $response): void
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
     * @param array<int, non-empty-string> $stack
     *
     * @return array<int, non-empty-string>
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
     * Entries are matched by their name without parameters, so
     * `'Foo:a,b'` sorts like `Foo`.
     *
     * @param array<int, non-empty-string> $stack
     *
     * @return array<int, non-empty-string>
     */
    private function sortByPriority(array $stack): array
    {
        if ($this->priority === []) {
            return $stack;
        }

        $prioritized = [];
        $rest = [];

        foreach ($stack as $entry) {
            if (in_array(self::parse($entry)[0], $this->priority, true)) {
                $prioritized[] = $entry;
            } else {
                $rest[] = $entry;
            }
        }

        usort($prioritized, function (string $a, string $b): int {
            $posA = array_search(self::parse($a)[0], $this->priority, true);
            $posB = array_search(self::parse($b)[0], $this->priority, true);
            return ($posA === false ? PHP_INT_MAX : $posA) <=> ($posB === false ? PHP_INT_MAX : $posB);
        });

        return array_merge($prioritized, $rest);
    }

    /**
     * @param callable(Request):ResponseInterface $terminal
     * @param array<int, non-empty-string>        $stack
     * @param int                                 $index
     *
     * @return callable(Request): ResponseInterface
     */
    private function buildPipeline(callable $terminal, array $stack, int $index): callable
    {
        if ($index >= count($stack)) {
            return $terminal;
        }

        return function (Request $request) use ($terminal, $stack, $index): ResponseInterface {
            [$name, $parameters] = self::parse($stack[$index]);
            $class = $this->aliases[$name] ?? $name;

            if (!class_exists($class) && !interface_exists($class)) {
                throw new ApplicationException(sprintf(
                    "Unknown middleware '%s': not a class, a registered alias or a middleware group.",
                    $stack[$index],
                ));
            }

            /** @var ContractsMiddlewareInterface $middleware */
            $middleware = $this->container->make($class);
            $this->resolved[] = $middleware;
            $next = $this->buildPipeline($terminal, $stack, $index + 1);

            if ($parameters === []) {
                return $middleware->handle($request, $next);
            }

            if (!$middleware instanceof ParameterizedMiddlewareInterface) {
                throw new ApplicationException(sprintf(
                    "Middleware '%s' was given parameters ('%s') but %s does not implement %s.",
                    $name,
                    $stack[$index],
                    $middleware::class,
                    ParameterizedMiddlewareInterface::class,
                ));
            }

            return $middleware->handle($request, $next, ...$parameters);
        };
    }

    /**
     * Split a middleware entry into its name and parameters.
     *
     * `'can:update,App\Post'` → `['can', ['update', 'App\Post']]`; an entry without
     * a colon, or with nothing after it, has no parameters. Class names and
     * aliases never contain `:`, so the first colon is always the separator.
     *
     * @param non-empty-string $entry
     *
     * @return array{0: string, 1: list<string>}
     */
    private static function parse(string $entry): array
    {
        $colon = strpos($entry, ':');

        if ($colon === false) {
            return [$entry, []];
        }

        $arguments = substr($entry, $colon + 1);

        return [substr($entry, 0, $colon), $arguments === '' ? [] : explode(',', $arguments)];
    }
}
