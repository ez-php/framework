<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Cookie;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\CsrfTokenStoreInterface;
use EzPhp\Middleware\XsrfCookieMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class XsrfCookieMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(XsrfCookieMiddleware::class)]
#[UsesClass(Cookie::class)]
final class XsrfCookieMiddlewareTest extends TestCase
{
    /**
     * A store reading the token through a closure, so a test can rotate it
     * mid-request the way Auth::login() does.
     *
     * @param \Closure(): string $token
     *
     * @return CsrfTokenStoreInterface
     */
    private function makeStore(\Closure $token): CsrfTokenStoreInterface
    {
        return new class ($token) implements CsrfTokenStoreInterface {
            /**
             * @param \Closure(): string $token
             */
            public function __construct(private readonly \Closure $token)
            {
            }

            public function getToken(): string
            {
                return ($this->token)();
            }
        };
    }

    /**
     * @param Response $response
     *
     * @return Cookie|null
     */
    private function xsrfCookie(Response $response): ?Cookie
    {
        foreach ($response->cookies() as $cookie) {
            if ($cookie->name() === 'XSRF-TOKEN') {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @return void
     */
    public function test_sets_readable_xsrf_cookie_with_the_session_token(): void
    {
        $middleware = new XsrfCookieMiddleware($this->makeStore(fn (): string => 'tok-1'));

        $response = $middleware->handle(new Request('GET', '/'), fn (Request $r): Response => new Response('ok'));

        $this->assertInstanceOf(Response::class, $response);
        $cookie = $this->xsrfCookie($response);
        $this->assertNotNull($cookie);
        $this->assertSame('tok-1', $cookie->value());
        $this->assertSame('/', $cookie->path());
        $this->assertFalse($cookie->isHttpOnly());
        $this->assertFalse($cookie->isSecure());
    }

    /**
     * @return void
     */
    public function test_cookie_carries_the_token_rotated_while_the_request_ran(): void
    {
        $token = 'before-login';
        $middleware = new XsrfCookieMiddleware($this->makeStore(function () use (&$token): string {
            return $token;
        }));

        $response = $middleware->handle(new Request('POST', '/login'), function (Request $r) use (&$token): Response {
            $token = 'after-login';

            return new Response('ok');
        });

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('after-login', $this->xsrfCookie($response)?->value());
    }

    /**
     * @return void
     */
    public function test_skips_the_cookie_when_the_client_already_holds_the_current_token(): void
    {
        $middleware = new XsrfCookieMiddleware($this->makeStore(fn (): string => 'tok-1'));

        $request = new Request('GET', '/', cookies: ['XSRF-TOKEN' => 'tok-1']);
        $response = $middleware->handle($request, fn (Request $r): Response => new Response('ok'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertNull($this->xsrfCookie($response));
    }

    /**
     * @return void
     */
    public function test_secure_flag_and_domain_are_configurable(): void
    {
        $middleware = new XsrfCookieMiddleware($this->makeStore(fn (): string => 'tok-1'), secure: true, domain: 'example.com');

        $response = $middleware->handle(new Request('GET', '/'), fn (Request $r): Response => new Response('ok'));

        $this->assertInstanceOf(Response::class, $response);
        $cookie = $this->xsrfCookie($response);
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('example.com', $cookie->domain());
    }
}
