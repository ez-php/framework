<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\DebugToolbarMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class DebugToolbarMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(DebugToolbarMiddleware::class)]
final class DebugToolbarMiddlewareTest extends TestCase
{
    /**
     * @return void
     */
    public function test_injects_toolbar_before_body_tag(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/hello');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('<html><body><p>Hello</p></body></html>'),
        );

        $this->assertStringContainsString('ez-debug-toolbar', $response->body());
        $this->assertStringContainsString('</body>', $response->body());
    }

    /**
     * @return void
     */
    public function test_toolbar_contains_method_and_uri(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('POST', '/api/users');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('<html><body></body></html>'),
        );

        $this->assertStringContainsString('POST', $response->body());
        $this->assertStringContainsString('/api/users', $response->body());
    }

    /**
     * @return void
     */
    public function test_toolbar_contains_status_code(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('<html><body></body></html>', 404),
        );

        $this->assertStringContainsString('404', $response->body());
    }

    /**
     * @return void
     */
    public function test_injects_before_html_tag_when_no_body_tag(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/');

        $body = '<html><p>no body tag</p></html>';

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response($body),
        );

        $this->assertStringContainsString('ez-debug-toolbar', $response->body());
        $this->assertStringContainsString('</html>', $response->body());
        $this->assertStringNotContainsString('</body>', $response->body());
    }

    /**
     * @return void
     */
    public function test_skips_non_html_response_by_content_type(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/api');

        $original = (new Response('<html><body></body></html>'))
            ->withHeader('Content-Type', 'application/json');

        $response = $middleware->handle($request, fn (): Response => $original);

        $this->assertStringNotContainsString('ez-debug-toolbar', $response->body());
    }

    /**
     * @return void
     */
    public function test_skips_response_without_html_tags(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/api');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('{"ok":true}'),
        );

        $this->assertStringNotContainsString('ez-debug-toolbar', $response->body());
        $this->assertSame('{"ok":true}', $response->body());
    }

    /**
     * @return void
     */
    public function test_status_500_color_is_red(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('<html><body></body></html>', 500),
        );

        $this->assertStringContainsString('#e74c3c', $response->body());
    }

    /**
     * @return void
     */
    public function test_status_200_color_is_green(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('<html><body></body></html>', 200),
        );

        $this->assertStringContainsString('#27ae60', $response->body());
    }

    /**
     * @return void
     */
    public function test_xss_in_uri_is_escaped(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/<script>alert(1)</script>');

        $response = $middleware->handle(
            $request,
            fn (): Response => new Response('<html><body></body></html>'),
        );

        $this->assertStringNotContainsString('<script>', $response->body());
        $this->assertStringContainsString('&lt;script&gt;', $response->body());
    }

    /**
     * @return void
     */
    public function test_original_response_headers_are_preserved(): void
    {
        $middleware = new DebugToolbarMiddleware();
        $request = new Request('GET', '/');

        $original = (new Response('<html><body></body></html>'))
            ->withHeader('X-Custom', 'preserved');

        $response = $middleware->handle($request, fn (): Response => $original);

        $this->assertSame('preserved', $response->headers()['X-Custom']);
    }
}
