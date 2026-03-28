<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\CsrfMiddleware;
use EzPhp\Middleware\CsrfTokenStoreInterface;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class CsrfMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(CsrfMiddleware::class)]
#[UsesClass(Router::class)]
#[UsesClass(Route::class)]
final class CsrfMiddlewareTest extends TestCase
{
    /** @var string */
    private const TOKEN = 'test-csrf-token-abc123';

    /**
     * Build a CsrfTokenStoreInterface stub that always returns the given token.
     *
     * @param string $token
     *
     * @return CsrfTokenStoreInterface
     */
    private function makeStore(string $token = self::TOKEN): CsrfTokenStoreInterface
    {
        return new class ($token) implements CsrfTokenStoreInterface {
            public function __construct(private readonly string $tok)
            {
            }

            public function getToken(): string
            {
                return $this->tok;
            }
        };
    }

    /**
     * Build a CsrfMiddleware with a pre-configured router.
     *
     * @param Router                  $router
     * @param CsrfTokenStoreInterface $store
     *
     * @return CsrfMiddleware
     */
    private function makeMiddleware(Router $router, CsrfTokenStoreInterface $store): CsrfMiddleware
    {
        return new CsrfMiddleware($router, $store);
    }

    /**
     * @return void
     */
    public function test_get_request_passes_through_without_token(): void
    {
        $router = new Router();
        $router->get('/page', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('GET', '/page');
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_head_and_options_pass_through(): void
    {
        $router = new Router();
        $middleware = $this->makeMiddleware($router, $this->makeStore());

        foreach (['HEAD', 'OPTIONS'] as $method) {
            $response = $middleware->handle(
                new Request($method, '/'),
                fn (Request $r): Response => new Response('ok'),
            );
            $this->assertSame(200, $response->status(), "Method $method should pass");
        }
    }

    /**
     * @return void
     */
    public function test_post_with_valid_token_in_input_passes(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/submit', body: ['_token' => self::TOKEN]);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_post_with_valid_token_in_header_passes(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/submit', headers: ['x-csrf-token' => self::TOKEN]);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_post_with_wrong_token_returns_403(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/submit', body: ['_token' => 'wrong-token']);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(403, $response->status());
    }

    /**
     * @return void
     */
    public function test_post_without_token_returns_403(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/submit');
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(403, $response->status());
    }

    /**
     * @return void
     */
    public function test_post_to_csrf_exempt_route_passes_without_token(): void
    {
        $router = new Router();
        $router->post('/webhook', fn () => 'ok')->withoutCsrf();

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/webhook');
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(200, $response->status());
    }

    /**
     * A POST to an unknown route is still CSRF-checked (returns 403 without token).
     * The 404 will be produced later by the main dispatch.
     *
     * @return void
     */
    public function test_post_to_unknown_route_without_token_returns_403(): void
    {
        $router = new Router();

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/does-not-exist');
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(403, $response->status());
    }

    /**
     * A POST to an unknown route with a valid token passes through (404 from dispatch).
     *
     * @return void
     */
    public function test_post_to_unknown_route_with_valid_token_passes(): void
    {
        $router = new Router();

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/does-not-exist', body: ['_token' => self::TOKEN]);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_rate_limiter_returns_429_when_limit_exceeded(): void
    {
        $router = new Router();
        $router->post('/login', fn () => 'ok');

        $limiter = new class () implements \EzPhp\Middleware\CsrfRateLimiterInterface {
            public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
            {
                return false;
            }

            public function tooManyAttempts(string $key, int $maxAttempts): bool
            {
                return true;
            }
        };

        $middleware = new CsrfMiddleware($router, $this->makeStore(), $limiter);
        $request = new Request('POST', '/login', server: ['REMOTE_ADDR' => '1.2.3.4']);

        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(429, $response->status());
    }

    /**
     * @return void
     */
    public function test_rate_limiter_records_attempt_and_returns_403_when_under_limit(): void
    {
        $router = new Router();
        $router->post('/login', fn () => 'ok');

        $attempts = 0;
        $limiter = new class ($attempts) implements \EzPhp\Middleware\CsrfRateLimiterInterface {
            public function __construct(public int &$attempts)
            {
            }

            public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
            {
                $this->attempts++;
                return true;
            }

            public function tooManyAttempts(string $key, int $maxAttempts): bool
            {
                return false;
            }
        };

        $middleware = new CsrfMiddleware($router, $this->makeStore(), $limiter);
        $request = new Request('POST', '/login', server: ['REMOTE_ADDR' => '1.2.3.4']);

        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(403, $response->status());
        $this->assertSame(1, $attempts);
    }

    /**
     * @return void
     */
    public function test_rate_limiter_not_called_when_token_is_valid(): void
    {
        $router = new Router();
        $router->post('/login', fn () => 'ok');

        $called = false;
        $limiter = new class ($called) implements \EzPhp\Middleware\CsrfRateLimiterInterface {
            public function __construct(public bool &$called)
            {
            }

            public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
            {
                $this->called = true;
                return true;
            }

            public function tooManyAttempts(string $key, int $maxAttempts): bool
            {
                $this->called = true;
                return false;
            }
        };

        $middleware = new CsrfMiddleware($router, $this->makeStore(), $limiter);
        $request = new Request('POST', '/login', body: ['_token' => self::TOKEN]);

        $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertFalse($called);
    }

    /**
     * @return void
     */
    public function test_put_and_patch_and_delete_require_token(): void
    {
        foreach (['PUT', 'PATCH', 'DELETE'] as $method) {
            $router = new Router();
            $router->add($method, '/resource', fn () => 'ok');

            $middleware = $this->makeMiddleware($router, $this->makeStore());

            $withToken = new Request($method, '/resource', body: ['_token' => self::TOKEN]);
            $withoutToken = new Request($method, '/resource');

            $this->assertSame(
                200,
                $middleware->handle($withToken, fn (Request $r): Response => new Response('ok'))->status(),
                "$method with valid token should pass",
            );
            $this->assertSame(
                403,
                $middleware->handle($withoutToken, fn (Request $r): Response => new Response('ok'))->status(),
                "$method without token should be 403",
            );
        }
    }

    /**
     * `_token` present but not a string (e.g. array via form manipulation) → 403.
     *
     * @return void
     */
    public function test_rejects_when_token_input_is_array(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/submit', body: ['_token' => ['nested', 'array']]);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(403, $response->status());
    }

    /**
     * X-CSRF-TOKEN header present but not a string → 403.
     *
     * @return void
     */
    public function test_rejects_when_token_header_is_non_string(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $middleware = $this->makeMiddleware($router, $this->makeStore());

        $request = new Request('POST', '/submit', headers: ['x-csrf-token' => ['array', 'value']]);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertSame(403, $response->status());
    }
}
