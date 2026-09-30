<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Maintenance\MaintenanceMode;
use EzPhp\Middleware\MaintenanceModeMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class MaintenanceModeMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(MaintenanceModeMiddleware::class)]
#[UsesClass(MaintenanceMode::class)]
final class MaintenanceModeMiddlewareTest extends TestCase
{
    private string $marker;

    private MaintenanceMode $mode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = sys_get_temp_dir() . '/ez-maint-mw-' . bin2hex(random_bytes(4)) . '/down';
        $this->mode = new MaintenanceMode($this->marker);
    }

    protected function tearDown(): void
    {
        @unlink($this->marker);
        @rmdir(dirname($this->marker));
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $cookies
     */
    private function through(string $uri = '/', array $headers = [], array $cookies = []): Response
    {
        $response = (new MaintenanceModeMiddleware($this->mode))->handle(
            new Request('GET', $uri, headers: $headers, cookies: $cookies),
            static fn (): Response => new Response('app'),
        );
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }

    public function test_passes_through_while_up(): void
    {
        self::assertSame('app', $this->through()->body());
    }

    public function test_returns_503_with_retry_after_while_down(): void
    {
        $this->mode->activate(retryAfter: 60);

        $response = $this->through();

        self::assertSame(503, $response->status());
        self::assertSame('60', $response->headers()['Retry-After']);
        self::assertStringContainsString('maintenance', $response->body());
    }

    public function test_omits_retry_after_when_not_given(): void
    {
        $this->mode->activate();

        self::assertArrayNotHasKey('Retry-After', $this->through()->headers());
    }

    public function test_returns_json_for_api_requests(): void
    {
        $this->mode->activate();

        $response = $this->through('/api/users', ['Accept' => 'application/json']);

        self::assertSame(503, $response->status());
        self::assertSame('{"error":{"code":503,"message":"Service Unavailable"}}', $response->body());
    }

    public function test_secret_path_sets_the_bypass_cookie_and_redirects(): void
    {
        $this->mode->activate(secret: 'let-me-in');

        $response = $this->through('/let-me-in');

        self::assertSame(302, $response->status());
        self::assertSame('/', $response->headers()['Location']);
        $cookie = $response->cookies()[0];
        self::assertSame(MaintenanceMode::BYPASS_COOKIE, $cookie->name());
        self::assertSame($this->mode->bypassToken(), $cookie->value());
        self::assertStringContainsString('HttpOnly', $cookie->toHeaderValue());
    }

    public function test_bypass_cookie_lets_the_request_through(): void
    {
        $this->mode->activate(secret: 'let-me-in');

        $response = $this->through('/', cookies: [MaintenanceMode::BYPASS_COOKIE => (string) $this->mode->bypassToken()]);

        self::assertSame('app', $response->body());
    }

    public function test_wrong_cookie_or_path_is_still_blocked(): void
    {
        $this->mode->activate(secret: 'let-me-in');

        self::assertSame(503, $this->through('/', cookies: [MaintenanceMode::BYPASS_COOKIE => 'forged'])->status());
        self::assertSame(503, $this->through('/let-me')->status());
    }

    public function test_no_secret_means_no_bypass_path(): void
    {
        $this->mode->activate();

        self::assertSame(503, $this->through('/anything')->status());
    }
}
