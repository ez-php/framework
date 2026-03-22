<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Container\Container;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Middleware\MiddlewareInterface;
use EzPhp\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class MiddlewareHandlerTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(MiddlewareHandler::class)]
#[UsesClass(Container::class)]
#[UsesClass(Route::class)]
final class MiddlewareHandlerTest extends TestCase
{
    /**
     * @param string $body
     *
     * @return Route
     */
    private function makeRoute(string $body = 'ok'): Route
    {
        return new Route('GET', '/', fn (Request $r): Response => new Response($body));
    }

    /**
     * @return void
     */
    public function test_handle_with_no_middleware_calls_route_directly(): void
    {
        $handler = new MiddlewareHandler(new Container());
        $response = $handler->handle($this->makeRoute('direct'), new Request('GET', '/'));

        $this->assertSame('direct', $response->body());
        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_single_middleware_wraps_request(): void
    {
        $container = new Container();
        $container->bind(AppendMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(AppendMiddleware::class);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        $this->assertSame('handler+appended', $response->body());
    }

    /**
     * @return void
     */
    public function test_multiple_middleware_execute_in_correct_order(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);
        $container->bind(PrefixBMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(PrefixAMiddleware::class);
        $handler->add(PrefixBMiddleware::class);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        // A wraps B wraps handler → "A>B>handler"
        $this->assertSame('A>B>handler', $response->body());
    }

    /**
     * @return void
     */
    public function test_route_middleware_executes_after_global_middleware(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);
        $container->bind(PrefixBMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(PrefixAMiddleware::class);

        $route = new Route('GET', '/', fn (Request $r): Response => new Response('handler'));
        $route->middleware(PrefixBMiddleware::class);

        $response = $handler->handle($route, new Request('GET', '/'));

        // global A wraps route B wraps handler → "A>B>handler"
        $this->assertSame('A>B>handler', $response->body());
    }

    /**
     * @return void
     */
    public function test_route_middleware_without_global_middleware(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);

        $handler = new MiddlewareHandler($container);

        $route = new Route('GET', '/', fn (Request $r): Response => new Response('handler'));
        $route->middleware(PrefixAMiddleware::class);

        $response = $handler->handle($route, new Request('GET', '/'));

        $this->assertSame('A>handler', $response->body());
    }

    /**
     * @return void
     */
    public function test_middleware_can_short_circuit(): void
    {
        $container = new Container();
        $container->bind(ShortCircuitMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(ShortCircuitMiddleware::class);

        $response = $handler->handle($this->makeRoute('should-not-reach'), new Request('GET', '/'));

        $this->assertSame('short-circuit', $response->body());
        $this->assertSame(403, $response->status());
    }

    // --- Priority Ordering (item 30) ---

    /**
     * @return void
     */
    public function test_set_priority_reorders_middleware(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);
        $container->bind(PrefixBMiddleware::class);

        $handler = new MiddlewareHandler($container);
        // Add B first, then A
        $handler->add(PrefixBMiddleware::class);
        $handler->add(PrefixAMiddleware::class);
        // Priority: A before B
        $handler->setPriority([PrefixAMiddleware::class, PrefixBMiddleware::class]);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        // A wraps B wraps handler → "A>B>handler"
        $this->assertSame('A>B>handler', $response->body());
    }

    /**
     * @return void
     */
    public function test_unprioritized_middleware_appended_in_original_order(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);
        $container->bind(PrefixBMiddleware::class);
        $container->bind(AppendMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(AppendMiddleware::class);
        $handler->add(PrefixAMiddleware::class);
        $handler->add(PrefixBMiddleware::class);
        // Only prioritize B before A; Append has no priority entry
        $handler->setPriority([PrefixBMiddleware::class, PrefixAMiddleware::class]);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        // B wraps A wraps (Append wraps handler) → "B>A>handler+appended"
        $this->assertSame('B>A>handler+appended', $response->body());
    }

    /**
     * @return void
     */
    public function test_duplicate_priority_entries_are_deduplicated(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);
        $container->bind(PrefixBMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(PrefixAMiddleware::class);
        $handler->add(PrefixBMiddleware::class);
        // A appears twice — should be treated as if listed once
        $handler->setPriority([PrefixAMiddleware::class, PrefixAMiddleware::class, PrefixBMiddleware::class]);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        // Deterministic: A wraps B wraps handler
        $this->assertSame('A>B>handler', $response->body());
    }

    /**
     * @return void
     */
    public function test_no_priority_preserves_original_order(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);
        $container->bind(PrefixBMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(PrefixAMiddleware::class);
        $handler->add(PrefixBMiddleware::class);
        // No setPriority call

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        $this->assertSame('A>B>handler', $response->body());
    }

    // --- Middleware Aliases (item 31) ---

    /**
     * @return void
     */
    public function test_alias_resolves_middleware_by_short_name(): void
    {
        $container = new Container();
        $container->bind(PrefixAMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->setAliases(['prefix-a' => PrefixAMiddleware::class]);

        // The alias key is treated as the middleware identifier; the handler
        // resolves it to the real class via the alias table in buildPipeline().
        // We use a cast to string to avoid the literal type inference that
        // would make the @var annotation redundant at the assignment site.
        $aliasKey = sprintf('%s', 'prefix-a');

        /** @var class-string<MiddlewareInterface> $aliasKey */
        $handler->add($aliasKey);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        $this->assertSame('A>handler', $response->body());
    }

    /**
     * @return void
     */
    public function test_non_alias_class_string_still_resolved_directly(): void
    {
        $container = new Container();
        $container->bind(PrefixBMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->setAliases(['other' => PrefixAMiddleware::class]);
        $handler->add(PrefixBMiddleware::class);

        $response = $handler->handle($this->makeRoute('handler'), new Request('GET', '/'));

        $this->assertSame('B>handler', $response->body());
    }
}

/**
 * Appends "+appended" to the inner response body.
 */
final class AppendMiddleware implements MiddlewareInterface
{
    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        return new Response($response->body() . '+appended', $response->status());
    }
}

/**
 * Prepends "A>" to the inner response body.
 */
final class PrefixAMiddleware implements MiddlewareInterface
{
    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        return new Response('A>' . $response->body(), $response->status());
    }
}

/**
 * Prepends "B>" to the inner response body.
 */
final class PrefixBMiddleware implements MiddlewareInterface
{
    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        return new Response('B>' . $response->body(), $response->status());
    }
}

/**
 * Returns early without calling $next.
 */
final class ShortCircuitMiddleware implements MiddlewareInterface
{
    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        return new Response('short-circuit', 403);
    }
}
