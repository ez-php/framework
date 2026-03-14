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
     * Run the full pipeline (global + route middleware) then the route handler.
     *
     * @param Route   $route
     * @param Request $request
     *
     * @return Response
     */
    public function handle(Route $route, Request $request): Response
    {
        $stack = array_merge($this->middleware, $route->getMiddleware());
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

        return $this->buildPipeline($terminal, $this->middleware, 0)($request);
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
            $route->getMiddleware(),
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
            $middleware = $this->container->make($stack[$index]);
            $this->resolved[] = $middleware;
            return $middleware->handle($request, $this->buildPipeline($terminal, $stack, $index + 1));
        };
    }
}
