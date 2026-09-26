<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Headers;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\CorsMiddleware;
use InvalidArgumentException;
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
     * A CORS preflight: OPTIONS carrying Access-Control-Request-Method.
     *
     * @param string $origin
     *
     * @return Request
     */
    private function preflight(string $origin = 'https://app.example.com'): Request
    {
        return new Request('OPTIONS', '/api/users', [], [], [
            'Origin' => $origin,
            'Access-Control-Request-Method' => 'POST',
        ]);
    }

    /**
     * @return void
     */
    public function test_preflight_request_returns_204_without_calling_next(): void
    {
        $middleware = new CorsMiddleware();
        $called = false;

        $response = $middleware->handle(
            $this->preflight(),
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
            $this->preflight(),
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
        self::assertInstanceOf(Response::class, $response);
        $this->assertSame('created', $response->body());
    }

    /**
     * @return void
     */
    public function test_disabled_middleware_passes_through_without_cors_headers(): void
    {
        $middleware = new CorsMiddleware(enabled: false);

        $response = $middleware->handle(
            new Request('GET', '/api/users'),
            fn (): Response => new Response('ok'),
        );

        self::assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->body());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers());
    }

    /**
     * @return void
     */
    public function test_disabled_middleware_does_not_intercept_options(): void
    {
        $middleware = new CorsMiddleware(enabled: false);
        $called = false;

        $response = $middleware->handle(
            new Request('OPTIONS', '/api/users'),
            function () use (&$called): Response {
                $called = true;
                return new Response('from next', 200);
            },
        );

        $this->assertTrue($called);
        $this->assertSame(200, $response->status());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers());
    }

    /**
     * @return void
     */
    public function test_plain_options_request_without_preflight_header_reaches_the_router(): void
    {
        $middleware = new CorsMiddleware();

        $response = $middleware->handle(
            new Request('OPTIONS', '/api/users'),
            fn (): Response => new Response('allow list', 200),
        );

        self::assertInstanceOf(Response::class, $response);
        $this->assertSame('allow list', $response->body());
        $this->assertSame('*', $response->headers()['Access-Control-Allow-Origin']);
    }

    /**
     * @return void
     */
    public function test_origin_list_reflects_a_matching_request_origin_and_varies_on_origin(): void
    {
        $middleware = new CorsMiddleware(allowOrigin: 'https://app.example.com, https://admin.example.com');

        $response = $middleware->handle(
            new Request('GET', '/', [], [], ['Origin' => 'https://admin.example.com']),
            fn (): Response => new Response('ok'),
        );

        $this->assertSame('https://admin.example.com', $response->headers()['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $response->headers()['Vary']);
    }

    /**
     * @return void
     */
    public function test_origin_list_omits_allow_origin_for_an_unlisted_origin(): void
    {
        $middleware = new CorsMiddleware(allowOrigin: 'https://app.example.com, https://admin.example.com');

        $response = $middleware->handle(
            $this->preflight('https://evil.example.net'),
            fn (): Response => new Response('never'),
        );

        $this->assertSame(204, $response->status());
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers());
        $this->assertSame('Origin', $response->headers()['Vary']);
    }

    /**
     * @return void
     */
    public function test_origin_list_appends_to_an_existing_vary_header(): void
    {
        $middleware = new CorsMiddleware(allowOrigin: 'https://app.example.com, https://admin.example.com');

        $response = $middleware->handle(
            new Request('GET', '/', [], [], ['Origin' => 'https://app.example.com']),
            fn (): Response => (new Response('ok'))->withHeader('vary', 'Accept-Encoding'),
        );

        $this->assertSame('Accept-Encoding, Origin', Headers::get($response->headers(), 'Vary'));
        $this->assertCount(1, array_filter(array_keys($response->headers()), static fn (string $name): bool => strtolower($name) === 'vary'));
    }

    /**
     * @return void
     */
    public function test_origin_list_does_not_duplicate_origin_in_vary(): void
    {
        $middleware = new CorsMiddleware(allowOrigin: 'https://app.example.com, https://admin.example.com');

        $response = $middleware->handle(
            new Request('GET', '/', [], [], ['Origin' => 'https://app.example.com']),
            fn (): Response => (new Response('ok'))->withHeader('Vary', 'Accept, origin'),
        );

        $this->assertSame('Accept, origin', $response->headers()['Vary']);
    }

    /**
     * @return void
     */
    public function test_allow_credentials_adds_the_credentials_header(): void
    {
        $middleware = new CorsMiddleware(allowOrigin: 'https://app.example.com', allowCredentials: true);

        $response = $middleware->handle(
            new Request('GET', '/', [], [], ['Origin' => 'https://app.example.com']),
            fn (): Response => new Response('ok'),
        );

        $this->assertSame('true', $response->headers()['Access-Control-Allow-Credentials']);
        $this->assertSame('https://app.example.com', $response->headers()['Access-Control-Allow-Origin']);
    }

    /**
     * @return void
     */
    public function test_credentials_are_not_sent_by_default(): void
    {
        $response = (new CorsMiddleware())->handle(new Request('GET', '/'), fn (): Response => new Response('ok'));

        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $response->headers());
    }

    /**
     * @return void
     */
    public function test_wildcard_origin_with_credentials_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CorsMiddleware(allowOrigin: '*', allowCredentials: true);
    }
}
