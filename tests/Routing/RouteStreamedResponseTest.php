<?php

declare(strict_types=1);

namespace Tests\Routing;

use EzPhp\Container\Container;
use EzPhp\Http\HeaderValidator;
use EzPhp\Http\Request;
use EzPhp\Http\StreamedResponse;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Routing\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class RouteStreamedResponseTest
 *
 * A controller may return a StreamedResponse; neither the route nor the
 * middleware pipeline may convert or reject it.
 *
 * @package Tests\Routing
 */
#[CoversClass(Route::class)]
#[CoversClass(MiddlewareHandler::class)]
#[UsesClass(Container::class)]
#[UsesClass(StreamedResponse::class)]
#[UsesClass(HeaderValidator::class)]
final class RouteStreamedResponseTest extends TestCase
{
    /**
     * @return void
     */
    public function test_route_returns_a_streamed_response_untouched(): void
    {
        $streamed = new StreamedResponse(fn (): iterable => ['a']);
        $route = new Route('GET', '/stream', fn (Request $r): StreamedResponse => $streamed);

        $this->assertSame($streamed, $route->run(new Request('GET', '/stream')));
    }

    /**
     * @return void
     */
    public function test_pipeline_returns_a_streamed_response_untouched(): void
    {
        $streamed = new StreamedResponse(fn (): iterable => ['a']);
        $route = new Route('GET', '/stream', fn (Request $r): StreamedResponse => $streamed);

        $response = (new MiddlewareHandler(new Container()))->handle($route, new Request('GET', '/stream'));

        $this->assertSame($streamed, $response);
    }
}
