<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Container\Container;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Middleware\MiddlewareInterface;
use EzPhp\Middleware\TerminableMiddleware;
use EzPhp\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class TerminableMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(MiddlewareHandler::class)]
#[UsesClass(Container::class)]
#[UsesClass(Route::class)]
final class TerminableMiddlewareTest extends TestCase
{
    /**
     * @return void
     */
    public function test_terminate_is_called_on_terminable_middleware(): void
    {
        $container = new Container();
        $container->bind(RecordingTerminableMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(RecordingTerminableMiddleware::class);

        $request = new Request('GET', '/');
        $route = new Route('GET', '/', fn (Request $r): Response => new Response('body'));

        $response = $handler->handle($route, $request);
        $handler->terminate($request, $response);

        $this->assertTrue(RecordingTerminableMiddleware::$terminated);
    }

    /**
     * @return void
     */
    public function test_terminate_is_not_called_on_non_terminable_middleware(): void
    {
        $container = new Container();
        $container->bind(NonTerminableTrackingMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(NonTerminableTrackingMiddleware::class);

        $request = new Request('GET', '/');
        $route = new Route('GET', '/', fn (Request $r): Response => new Response('body'));

        $response = $handler->handle($route, $request);
        $handler->terminate($request, $response);

        $this->assertFalse(NonTerminableTrackingMiddleware::$terminated);
    }

    /**
     * @return void
     */
    public function test_terminate_passes_correct_request_and_response(): void
    {
        $container = new Container();
        $container->bind(CapturingTerminableMiddleware::class);

        $handler = new MiddlewareHandler($container);
        $handler->add(CapturingTerminableMiddleware::class);

        $request = new Request('GET', '/captured');
        $route = new Route('GET', '/captured', fn (Request $r): Response => new Response('captured-body'));

        $response = $handler->handle($route, $request);
        $handler->terminate($request, $response);

        $this->assertSame('/captured', CapturingTerminableMiddleware::$capturedRequest?->uri());
        $this->assertSame('captured-body', CapturingTerminableMiddleware::$capturedResponse?->body());
    }

    /**
     * @return void
     */
    public function test_terminate_does_nothing_when_no_middleware_resolved(): void
    {
        $handler = new MiddlewareHandler(new Container());

        $handler->terminate(new Request('GET', '/'), new Response('ok'));

        $this->expectNotToPerformAssertions();
    }
}

/**
 * Records that terminate() was called.
 */
final class RecordingTerminableMiddleware implements TerminableMiddleware
{
    public static bool $terminated = false;

    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response */
        return $next($request);
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        self::$terminated = true;
    }
}

/**
 * Tracks if terminate() is ever invoked (it should not be).
 */
final class NonTerminableTrackingMiddleware implements MiddlewareInterface
{
    public static bool $terminated = false;

    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response */
        return $next($request);
    }
}

/**
 * Captures the request and response passed to terminate().
 */
final class CapturingTerminableMiddleware implements TerminableMiddleware
{
    public static ?Request $capturedRequest = null;

    public static ?Response $capturedResponse = null;

    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        /** @var Response */
        return $next($request);
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        self::$capturedRequest = $request;
        self::$capturedResponse = $response;
    }
}
