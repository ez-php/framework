<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\CorsMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class CorsMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(CorsMiddleware::class)]
final class CorsMiddlewareTest extends TestCase
{
    /**
     * @return void
     */
    public function test_options_request_returns_204_without_calling_next(): void
    {
        $middleware = new CorsMiddleware();
        $called = false;

        $response = $middleware->handle(
            new Request('OPTIONS', '/api/users'),
            function () use (&$called): Response {
                $called = true;
                return new Response('should not reach');
            },
        );

        $this->assertFalse($called);
        $this->assertSame(204, $response->status());
    }

    /**
     * @return void
     */
    public function test_cors_headers_are_added_to_response(): void
    {
        $middleware = new CorsMiddleware();

        $response = $middleware->handle(
            new Request('GET', '/api/users'),
            fn (): Response => new Response('ok'),
        );

        $headers = $response->headers();

        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $headers);
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $headers);
        $this->assertArrayHasKey('Access-Control-Max-Age', $headers);
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * @return void
     */
    public function test_cors_headers_are_added_to_preflight_response(): void
    {
        $middleware = new CorsMiddleware();

        $response = $middleware->handle(
            new Request('OPTIONS', '/api/users'),
            fn (): Response => new Response('ok'),
        );

        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->headers());
    }

    /**
     * @return void
     */
    public function test_custom_allow_origin(): void
    {
        $middleware = new CorsMiddleware(allowOrigin: 'https://example.com');

        $response = $middleware->handle(
            new Request('GET', '/'),
            fn (): Response => new Response('ok'),
        );

        $this->assertSame('https://example.com', $response->headers()['Access-Control-Allow-Origin']);
    }

    /**
     * @return void
     */
    public function test_custom_max_age(): void
    {
        $middleware = new CorsMiddleware(maxAge: 3600);

        $response = $middleware->handle(
            new Request('GET', '/'),
            fn (): Response => new Response('ok'),
        );

        $this->assertSame('3600', $response->headers()['Access-Control-Max-Age']);
    }

    /**
     * @return void
     */
    public function test_non_options_request_calls_next(): void
    {
        $middleware = new CorsMiddleware();

        $response = $middleware->handle(
            new Request('POST', '/api/users'),
            fn (): Response => new Response('created', 201),
        );

        $this->assertSame(201, $response->status());
        $this->assertSame('created', $response->body());
    }
}
