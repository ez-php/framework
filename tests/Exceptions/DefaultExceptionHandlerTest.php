<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\DebugHtmlRenderer;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ProductionHtmlRenderer;
use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use EzPhp\I18n\Translator;
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
#[UsesClass(DebugHtmlRenderer::class)]
#[UsesClass(ProductionHtmlRenderer::class)]
#[UsesClass(Translator::class)]
final class DefaultExceptionHandlerTest extends TestCase
{
    /**
     * @return void
     */
    public function test_render_returns_404_for_route_exception(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/missing');
        $response = $handler->render(new RouteException(), $request);

        $this->assertSame(404, $response->status());
        $this->assertSame('text/html; charset=utf-8', $response->headers()['Content-Type']);
        $this->assertStringContainsString('404', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_500_html_with_generic_message_in_production(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/');
        $response = $handler->render(new RuntimeException('Something broke'), $request);

        $this->assertSame(500, $response->status());
        $this->assertSame('text/html; charset=utf-8', $response->headers()['Content-Type']);
        $this->assertStringContainsString('500', $response->body());
        $this->assertStringNotContainsString('Something broke', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_debug_html_with_exception_details_in_debug_mode(): void
    {
        $handler = new DefaultExceptionHandler(debug: true);
        $request = new Request('GET', '/');
        $response = $handler->render(new RuntimeException('Something broke'), $request);

        $this->assertSame(500, $response->status());
        $this->assertSame('text/html; charset=utf-8', $response->headers()['Content-Type']);
        $this->assertStringContainsString('RuntimeException', $response->body());
        $this->assertStringContainsString('Something broke', $response->body());
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

    /**
     * @return void
     */
    public function test_render_uses_custom_template_when_available(): void
    {
        $templatePath = sys_get_temp_dir() . '/ez-php-error-test-' . uniqid();
        mkdir($templatePath);
        file_put_contents($templatePath . '/404.php', '<h1>Custom 404</h1>');

        $handler = new DefaultExceptionHandler(debug: false, templatePath: $templatePath);
        $request = new Request('GET', '/missing');
        $response = $handler->render(new RouteException(), $request);

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('Custom 404', $response->body());

        unlink($templatePath . '/404.php');
        rmdir($templatePath);
    }

    /**
     * @return void
     */
    public function test_debug_html_shows_request_method_and_uri(): void
    {
        $handler = new DefaultExceptionHandler(debug: true);
        $request = new Request('POST', '/api/users');
        $response = $handler->render(new RuntimeException('Failure'), $request);

        $this->assertStringContainsString('POST', $response->body());
        $this->assertStringContainsString('/api/users', $response->body());
    }
}
