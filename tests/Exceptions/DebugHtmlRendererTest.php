<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\DebugHtmlRenderer;
use EzPhp\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class DebugHtmlRendererTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(DebugHtmlRenderer::class)]
final class DebugHtmlRendererTest extends TestCase
{
    /**
     * @return void
     */
    public function test_render_contains_exception_class(): void
    {
        $html = (new DebugHtmlRenderer())->render(
            new RuntimeException('Oops'),
            new Request('GET', '/'),
        );

        $this->assertStringContainsString('RuntimeException', $html);
    }

    /**
     * @return void
     */
    public function test_render_contains_exception_message(): void
    {
        $html = (new DebugHtmlRenderer())->render(
            new RuntimeException('Something went wrong'),
            new Request('GET', '/'),
        );

        $this->assertStringContainsString('Something went wrong', $html);
    }

    /**
     * @return void
     */
    public function test_render_contains_file_and_line(): void
    {
        $e = new RuntimeException('err');
        $html = (new DebugHtmlRenderer())->render($e, new Request('GET', '/'));

        $this->assertStringContainsString($e->getFile(), $html);
        $this->assertStringContainsString((string) $e->getLine(), $html);
    }

    /**
     * @return void
     */
    public function test_render_contains_request_method_and_uri(): void
    {
        $html = (new DebugHtmlRenderer())->render(
            new RuntimeException('err'),
            new Request('POST', '/api/login'),
        );

        $this->assertStringContainsString('POST', $html);
        $this->assertStringContainsString('/api/login', $html);
    }

    /**
     * @return void
     */
    public function test_render_contains_stack_trace(): void
    {
        $html = (new DebugHtmlRenderer())->render(
            new RuntimeException('err'),
            new Request('GET', '/'),
        );

        $this->assertStringContainsString('Stack Trace', $html);
    }

    /**
     * @return void
     */
    public function test_render_escapes_html_in_message(): void
    {
        $html = (new DebugHtmlRenderer())->render(
            new RuntimeException('<script>alert(1)</script>'),
            new Request('GET', '/'),
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * @return void
     */
    public function test_render_returns_valid_html_document(): void
    {
        $html = (new DebugHtmlRenderer())->render(
            new RuntimeException('err'),
            new Request('GET', '/'),
        );

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('</html>', $html);
    }
}
