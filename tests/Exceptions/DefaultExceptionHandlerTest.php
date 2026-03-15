<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class DefaultExceptionHandlerTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(DefaultExceptionHandler::class)]
#[UsesClass(RouteException::class)]
final class DefaultExceptionHandlerTest extends TestCase
{
    /**
     * @return void
     */
    public function test_render_returns_404_for_route_exception(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/');

        $response = $handler->render(new RouteException(), $request);

        $this->assertSame(404, $response->status());
        $this->assertSame('Route not found', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_500_with_generic_message_in_production(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/');

        $response = $handler->render(new RuntimeException('Something broke'), $request);

        $this->assertSame(500, $response->status());
        $this->assertSame('Internal Server Error', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_500_with_real_message_in_debug_mode(): void
    {
        $handler = new DefaultExceptionHandler(debug: true);
        $request = new Request('GET', '/');

        $response = $handler->render(new RuntimeException('Something broke'), $request);

        $this->assertSame(500, $response->status());
        $this->assertSame('Something broke', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_json_when_accept_header_is_json(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);

        $response = $handler->render(new RuntimeException('Oops'), $request);

        $this->assertSame(500, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertSame('{"error":"Internal Server Error"}', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_json_with_real_message_in_debug_mode(): void
    {
        $handler = new DefaultExceptionHandler(debug: true);
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);

        $response = $handler->render(new RuntimeException('Oops'), $request);

        $this->assertSame(500, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertSame('{"error":"Oops"}', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_json_404_for_route_exception_with_json_accept(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);

        $response = $handler->render(new RouteException(), $request);

        $this->assertSame(404, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertStringContainsString('Route not found', $response->body());
    }
}
