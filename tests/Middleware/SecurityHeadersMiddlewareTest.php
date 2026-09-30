<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Config\Config;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class SecurityHeadersMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(SecurityHeadersMiddleware::class)]
#[UsesClass(Config::class)]
final class SecurityHeadersMiddlewareTest extends TestCase
{
    /**
     * @param \Closure(): ResponseInterface|null $next
     *
     * @return array<string, string>
     */
    private function headersOf(SecurityHeadersMiddleware $middleware, ?\Closure $next = null): array
    {
        $response = $middleware->handle(new Request('GET', '/'), $next ?? static fn (): Response => new Response('ok'));

        return array_change_key_case($response->headers());
    }

    public function test_defaults_are_sent(): void
    {
        $headers = $this->headersOf(new SecurityHeadersMiddleware());

        self::assertSame('nosniff', $headers['x-content-type-options']);
        self::assertSame('DENY', $headers['x-frame-options']);
        self::assertSame('strict-origin-when-cross-origin', $headers['referrer-policy']);
        self::assertSame('max-age=31536000; includeSubDomains', $headers['strict-transport-security']);
        self::assertArrayNotHasKey('content-security-policy', $headers);
    }

    public function test_constructor_headers_override_add_and_remove(): void
    {
        $headers = $this->headersOf(new SecurityHeadersMiddleware([
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "default-src 'self'",
            'Strict-Transport-Security' => null,
        ]));

        self::assertSame('SAMEORIGIN', $headers['x-frame-options']);
        self::assertSame("default-src 'self'", $headers['content-security-policy']);
        self::assertArrayNotHasKey('strict-transport-security', $headers);
    }

    public function test_override_keys_are_case_insensitive(): void
    {
        $headers = $this->headersOf(new SecurityHeadersMiddleware(['x-frame-options' => 'SAMEORIGIN']));

        self::assertSame('SAMEORIGIN', $headers['x-frame-options']);
        self::assertCount(1, array_filter(array_keys($headers), static fn (string $k): bool => $k === 'x-frame-options'));
    }

    public function test_config_headers_apply_and_constructor_wins_over_config(): void
    {
        $config = new Config(['security' => ['headers' => [
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]]]);

        $headers = $this->headersOf(new SecurityHeadersMiddleware(['X-Frame-Options' => 'DENY'], $config));

        self::assertSame('no-referrer', $headers['referrer-policy']);
        self::assertSame('DENY', $headers['x-frame-options']);
    }

    public function test_a_header_the_response_already_sets_is_kept(): void
    {
        $headers = $this->headersOf(
            new SecurityHeadersMiddleware(),
            static fn (): ResponseInterface => (new Response('embed'))->withHeader('x-frame-options', 'SAMEORIGIN'),
        );

        self::assertSame('SAMEORIGIN', $headers['x-frame-options']);
    }

    public function test_invalid_config_value_is_ignored(): void
    {
        $config = new Config(['security' => ['headers' => 'nope']]);

        $headers = $this->headersOf(new SecurityHeadersMiddleware([], $config));

        self::assertSame('DENY', $headers['x-frame-options']);
    }
}
