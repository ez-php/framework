<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Container\Container;
use EzPhp\Contracts\MiddlewareInterface as ContractsMiddlewareInterface;
use EzPhp\Http\HeaderValidator;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Http\StreamedResponse;
use EzPhp\Middleware\CorsMiddleware;
use EzPhp\Middleware\DebugToolbarMiddleware;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Routing\ResourceControllerInterface;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class StreamThroughMiddlewareTest
 *
 * The point of the 2.0 pipeline: a StreamedResponse passes through real
 * middleware, receives their headers, and is not buffered or converted.
 *
 * @package Tests\Middleware
 */
#[CoversClass(CorsMiddleware::class)]
#[CoversClass(DebugToolbarMiddleware::class)]
#[UsesClass(MiddlewareHandler::class)]
#[UsesClass(Container::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(StreamedResponse::class)]
#[UsesClass(HeaderValidator::class)]
final class StreamThroughMiddlewareTest extends TestCase
{
    /**
     * Framework middleware plus a module-style middleware (implementing only
     * the contracts interface, as ThrottleMiddleware or AuthMiddleware do),
     * both registered through the real MiddlewareHandler pipeline. The
     * module-style middleware is defined here rather than taken from
     * ez-php/rate-limiter: the framework package does not depend on it, so its
     * standalone CI cannot load module classes.
     *
     * @return void
     */
    public function test_streamed_response_passes_framework_and_module_middleware_and_receives_their_headers(): void
    {
        $produced = 0;
        $streamed = new StreamedResponse(function () use (&$produced): \Generator {
            $produced++;
            yield 'chunk';
        });

        $handler = new MiddlewareHandler(new Container());
        $handler->add(CorsMiddleware::class);
        $handler->add(ModuleStyleHeaderMiddleware::class);

        $route = new Route('GET', '/stream', fn (Request $r): StreamedResponse => $streamed);
        $response = $handler->handle($route, new Request('GET', '/stream'));

        self::assertInstanceOf(StreamedResponse::class, $response);
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->headers());
        $this->assertArrayHasKey('X-Module-Style', $response->headers());
        $this->assertSame(0, $produced, 'Middleware must not consume the stream.');
    }

    /**
     * A resource controller may stream too: Router::resource() wraps each
     * action in a typed closure, which must not narrow the return type.
     *
     * @return void
     */
    public function test_resource_route_passes_a_streamed_response_through(): void
    {
        $streamed = new StreamedResponse(fn (): iterable => ['a']);
        $router = new Router();
        $router->resource('streams', new StreamingResourceController($streamed));

        $request = new Request('GET', '/streams');

        $this->assertSame($streamed, $router->retrieveRoute($request)->run($request));
    }

    /**
     * @return void
     */
    public function test_debug_toolbar_leaves_a_streamed_response_untouched(): void
    {
        $container = new Container();
        $handler = new MiddlewareHandler($container);
        $handler->add(DebugToolbarMiddleware::class);

        $streamed = new StreamedResponse(fn (): iterable => ['</body>'], 200, ['Content-Type' => 'text/html']);
        $route = new Route('GET', '/stream', fn (Request $r): StreamedResponse => $streamed);

        $this->assertSame($streamed, $handler->handle($route, new Request('GET', '/stream')));
    }
}

/**
 * Resource controller whose index action streams.
 */
final class StreamingResourceController implements ResourceControllerInterface
{
    /**
     * @param StreamedResponse $streamed
     */
    public function __construct(private readonly StreamedResponse $streamed)
    {
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function index(Request $request): ResponseInterface
    {
        return $this->streamed;
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function create(Request $request): ResponseInterface
    {
        return new Response('create');
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function store(Request $request): ResponseInterface
    {
        return new Response('store');
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function show(Request $request): ResponseInterface
    {
        return new Response('show');
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function edit(Request $request): ResponseInterface
    {
        return new Response('edit');
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function update(Request $request): ResponseInterface
    {
        return new Response('update');
    }

    /**
     * @param Request $request
     *
     * @return ResponseInterface
     */
    public function destroy(Request $request): ResponseInterface
    {
        return new Response('destroy');
    }
}

/**
 * Stands in for module middleware: implements only EzPhp\Contracts\MiddlewareInterface.
 */
final class ModuleStyleHeaderMiddleware implements ContractsMiddlewareInterface
{
    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $next($request);

        return $response->withHeader('X-Module-Style', 'yes');
    }
}
