<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\DebugHtmlRenderer;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\HttpException;
use EzPhp\Exceptions\ProductionHtmlRenderer;
use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Class DefaultExceptionHandlerTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(DefaultExceptionHandler::class)]
#[UsesClass(RouteException::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(DebugHtmlRenderer::class)]
#[UsesClass(ProductionHtmlRenderer::class)]
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
        $this->assertSame('{"error":{"code":500,"message":"Internal Server Error"}}', $response->body());
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
        $this->assertSame('{"error":{"code":500,"message":"Oops"}}', $response->body());
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
        $this->assertSame('{"error":{"code":404,"message":"Route not found"}}', $response->body());
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

    // --- HttpException ---

    /**
     * @return void
     */
    public function test_render_uses_http_exception_status_code(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/');
        $response = $handler->render(new HttpException(403, 'Forbidden'), $request);

        $this->assertSame(403, $response->status());
    }

    /**
     * @return void
     */
    public function test_render_shows_http_exception_message_in_production(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new HttpException(403, 'Forbidden'), $request);

        $this->assertSame('{"error":{"code":403,"message":"Forbidden"}}', $response->body());
    }

    /**
     * @return void
     */
    public function test_render_returns_json_http_exception_with_json_accept(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new HttpException(422, 'Unprocessable'), $request);

        $this->assertSame(422, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
    }

    // --- Structured JSON Envelope (item 33) ---

    /**
     * @return void
     */
    public function test_json_error_contains_code_field(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new HttpException(404, 'Not Found'), $request);

        /** @var array{error: array{code: int, message: string}} $body */
        $body = json_decode($response->body(), true);
        $this->assertSame(404, $body['error']['code']);
        $this->assertSame('Not Found', $body['error']['message']);
    }

    /**
     * @return void
     */
    public function test_json_error_envelope_format(): void
    {
        $handler = new DefaultExceptionHandler();
        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new RouteException(), $request);

        $this->assertSame('{"error":{"code":404,"message":"Route not found"}}', $response->body());
    }

    // --- Custom Renderers (item 32) ---

    /**
     * @return void
     */
    public function test_renderable_custom_renderer_is_called_for_matching_exception(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->renderable(
            HttpException::class,
            function (Throwable $e, RequestInterface $r): Response {
                /** @var HttpException $e */
                return new Response('custom:' . $e->getStatusCode(), $e->getStatusCode());
            }
        );

        $request = new Request('GET', '/');
        $response = $handler->render(new HttpException(403, 'Forbidden'), $request);

        $this->assertSame(403, $response->status());
        $this->assertSame('custom:403', $response->body());
    }

    /**
     * @return void
     */
    public function test_renderable_first_matching_renderer_wins(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->renderable(
            HttpException::class,
            fn (Throwable $e, RequestInterface $r): Response => new Response('first', 200)
        );
        $handler->renderable(
            HttpException::class,
            fn (Throwable $e, RequestInterface $r): Response => new Response('second', 200)
        );

        $request = new Request('GET', '/');
        $response = $handler->render(new HttpException(404, 'Not Found'), $request);

        $this->assertSame('first', $response->body());
    }

    /**
     * @return void
     */
    public function test_renderable_falls_through_to_default_when_no_match(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->renderable(
            HttpException::class,
            fn (Throwable $e, RequestInterface $r): Response => new Response('custom', 200)
        );

        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new RouteException(), $request);

        // RouteException is not HttpException; falls through to default JSON handler
        $this->assertStringContainsString('Route not found', $response->body());
    }

    /**
     * @return void
     */
    public function test_renderable_returns_self_for_chaining(): void
    {
        $handler = new DefaultExceptionHandler();
        $result = $handler->renderable(
            HttpException::class,
            fn (Throwable $e, RequestInterface $r): Response => new Response('', 200)
        );

        $this->assertSame($handler, $result);
    }

    // --- Exception-to-HTTP mapping registry ---

    /**
     * @return void
     */
    public function test_map_returns_self_for_chaining(): void
    {
        $handler = new DefaultExceptionHandler();
        $result = $handler->map(RuntimeException::class, 422, 'RUNTIME_ERROR');

        $this->assertSame($handler, $result);
    }

    /**
     * Mapped exception produces the registered status instead of 500.
     *
     * @return void
     */
    public function test_map_uses_registered_status_for_json_response(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->map(RuntimeException::class, 422, 'RUNTIME_ERROR');

        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new RuntimeException('something went wrong'), $request);

        $this->assertSame(422, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
    }

    /**
     * String code from map() is used as JSON `code` field.
     *
     * @return void
     */
    public function test_map_uses_string_code_in_json_envelope(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->map(RuntimeException::class, 422, 'INSUFFICIENT_GOLD');

        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new RuntimeException('not enough gold'), $request);

        /** @var array{error: array{code: string, message: string}} $body */
        $body = json_decode($response->body(), true);
        $this->assertSame('INSUFFICIENT_GOLD', $body['error']['code']);
        $this->assertSame('not enough gold', $body['error']['message']);
    }

    /**
     * When no string code is provided, the numeric HTTP status is used as code.
     *
     * @return void
     */
    public function test_map_uses_numeric_status_as_code_when_no_string_code(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->map(RuntimeException::class, 409);

        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        $response = $handler->render(new RuntimeException('conflict'), $request);

        /** @var array{error: array{code: int, message: string}} $body */
        $body = json_decode($response->body(), true);
        $this->assertSame(409, $body['error']['code']);
    }

    /**
     * Subclasses of the mapped exception are also caught by the mapping.
     *
     * @return void
     */
    public function test_map_matches_subclass_of_registered_exception(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->map(\Exception::class, 400, 'BAD_REQUEST');

        $request = new Request('GET', '/', headers: ['accept' => 'application/json']);
        // RuntimeException extends Exception
        $response = $handler->render(new RuntimeException('sub'), $request);

        $this->assertSame(400, $response->status());
    }

    /**
     * renderable() takes priority over map() for the same exception class.
     *
     * @return void
     */
    public function test_renderable_takes_priority_over_map(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->renderable(
            RuntimeException::class,
            fn (Throwable $e, RequestInterface $r): Response => new Response('from-renderable', 200)
        );
        $handler->map(RuntimeException::class, 422, 'RUNTIME_ERROR');

        $request = new Request('GET', '/');
        $response = $handler->render(new RuntimeException('x'), $request);

        $this->assertSame('from-renderable', $response->body());
    }

    /**
     * Mapped exception falls through to HTML renderer for non-JSON requests.
     *
     * @return void
     */
    public function test_map_returns_html_for_non_json_request(): void
    {
        $handler = new DefaultExceptionHandler();
        $handler->map(RuntimeException::class, 422, 'RUNTIME_ERROR');

        $request = new Request('GET', '/');
        $response = $handler->render(new RuntimeException('oops'), $request);

        $this->assertSame(422, $response->status());
        $this->assertSame('text/html; charset=utf-8', $response->headers()['Content-Type']);
    }
}
