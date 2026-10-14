<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;

/**
 * Class XsrfCookieMiddleware
 *
 * Mirrors the CSRF token into a JavaScript-readable `XSRF-TOKEN` cookie so a
 * single-page app can echo it back in the `X-XSRF-TOKEN` header, which
 * CsrfMiddleware accepts (Angular's HttpClient and Axios do this out of the box).
 *
 * The cookie is written *after* the request ran, so a token rotated during the
 * request (e.g. by Auth::login()) reaches the client in the same response. It
 * is skipped when the request already carried the current token. The cookie is
 * deliberately not HttpOnly — script access is its purpose; the server-side
 * comparison against the session token is what CsrfMiddleware enforces.
 *
 * Opt-in; register it on the routes or globally where a session is active:
 *   $app->middleware(XsrfCookieMiddleware::class);
 *
 * @package EzPhp\Middleware
 */
final class XsrfCookieMiddleware implements MiddlewareInterface
{
    private const COOKIE = 'XSRF-TOKEN';

    /**
     * XsrfCookieMiddleware Constructor
     *
     * @param CsrfTokenStoreInterface $tokenStore Source of the current CSRF token.
     * @param bool                    $secure     Send the cookie over HTTPS only.
     * @param string                  $domain     Cookie domain; empty for the current host.
     * @param string                  $path       Cookie path.
     */
    public function __construct(
        private readonly CsrfTokenStoreInterface $tokenStore,
        private readonly bool $secure = false,
        private readonly string $domain = '',
        private readonly string $path = '/',
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $next($request);

        $token = $this->tokenStore->getToken();

        if ($request->cookie(self::COOKIE) === $token) {
            return $response;
        }

        return $response->withCookie(self::COOKIE, $token, 0, $this->path, $this->domain, $this->secure, false);
    }
}
