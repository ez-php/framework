<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Routing\Router;

/**
 * Class CsrfMiddleware
 *
 * Verifies the CSRF token for all state-changing requests (POST, PUT, PATCH,
 * DELETE). The token is read from the form input field `_token` or the
 * `X-CSRF-TOKEN` request header and compared against the value stored in the
 * CSRF token store (typically PHP's session).
 *
 * Routes can opt out of CSRF verification by calling `->withoutCsrf()` on the
 * Route object. CsrfMiddleware resolves the current route via the Router to
 * check this flag — if the route is not found (404), CSRF is skipped (the
 * request will fail at the routing stage anyway).
 *
 * Optional rate limiting on token mismatches:
 *   Pass a CsrfRateLimiterInterface instance to throttle repeated failures.
 *   On limit exceeded the middleware returns 429 instead of 403.
 *   Alternatively, place ThrottleMiddleware globally before CsrfMiddleware to
 *   throttle all requests regardless of token validity.
 *
 * Usage (in bootstrap or service provider):
 *   $app->middleware(CsrfMiddleware::class);
 *
 * @package EzPhp\Middleware
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * HTTP methods that do not mutate state and therefore bypass CSRF.
     *
     * @var list<string>
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * CsrfMiddleware Constructor
     *
     * @param Router                       $router      Used to check whether the matched route
     *                                                  is marked as CSRF-exempt via withoutCsrf().
     * @param CsrfTokenStoreInterface      $tokenStore  Backing store for the CSRF token.
     * @param CsrfRateLimiterInterface|null $rateLimiter Optional rate limiter; when provided,
     *                                                  token mismatches are recorded and the
     *                                                  response is 429 once the limit is exceeded.
     * @param int                          $maxAttempts  Maximum CSRF failures per window (default: 5).
     * @param int                          $decaySeconds Window length in seconds (default: 60).
     */
    public function __construct(
        private readonly Router $router,
        private readonly CsrfTokenStoreInterface $tokenStore,
        private readonly ?CsrfRateLimiterInterface $rateLimiter = null,
        private readonly int $maxAttempts = 5,
        private readonly int $decaySeconds = 60,
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return Response
     */
    public function handle(RequestInterface $request, callable $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        if ($this->router->isCsrfExemptRoute($request)) {
            return $next($request);
        }

        $sessionToken = $this->tokenStore->getToken();
        $requestToken = $this->extractToken($request);

        if (!hash_equals($sessionToken, $requestToken)) {
            return $this->handleTokenMismatch($request);
        }

        return $next($request);
    }

    /**
     * Handle a CSRF token mismatch: apply rate limiting when configured,
     * then return 429 on limit exceeded or 403 otherwise.
     *
     * @param RequestInterface $request
     *
     * @return Response
     */
    private function handleTokenMismatch(RequestInterface $request): Response
    {
        if ($this->rateLimiter === null) {
            return new Response('CSRF token mismatch.', 403);
        }

        $ip = $request->server('REMOTE_ADDR', 'unknown');
        $key = 'csrf_mismatch:' . (is_string($ip) ? $ip : 'unknown');

        if ($this->rateLimiter->tooManyAttempts($key, $this->maxAttempts)) {
            return new Response('Too Many Requests.', 429);
        }

        $this->rateLimiter->attempt($key, $this->maxAttempts, $this->decaySeconds);

        return new Response('CSRF token mismatch.', 403);
    }

    /**
     * Extract the CSRF token from form input `_token` or the X-CSRF-TOKEN header.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    private function extractToken(RequestInterface $request): string
    {
        $fromInput = $request->input('_token');
        if (is_string($fromInput) && $fromInput !== '') {
            return $fromInput;
        }

        $fromHeader = $request->header('X-CSRF-TOKEN');

        return is_string($fromHeader) ? $fromHeader : '';
    }
}
