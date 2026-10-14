<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use Closure;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Routing\Router;

/**
 * Class CsrfMiddleware
 *
 * Verifies the CSRF token for all state-changing requests (POST, PUT, PATCH,
 * DELETE). The token is read from the form input field `_token`, the
 * `X-CSRF-TOKEN` request header, or the `X-XSRF-TOKEN` header (what Angular and
 * Axios echo from the cookie set by XsrfCookieMiddleware) and compared against
 * the value stored in the CSRF token store (typically PHP's session).
 *
 * Rejections are plain text, or the framework's JSON error envelope
 * (`{"error":{"code":403,"message":"..."}}`, as DefaultExceptionHandler renders
 * it) when the Accept header asks for application/json. Pass a
 * `$mismatchResponse` factory to build the rejection yourself instead (e.g. a
 * 419 in an application-specific JSON shape).
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
     * @param (Closure(RequestInterface, int, string): ResponseInterface)|null $mismatchResponse
     *                                                  Optional factory for the rejection, called with
     *                                                  the request, the default status (403 or 429)
     *                                                  and message; replaces the built-in response.
     */
    public function __construct(
        private readonly Router $router,
        private readonly CsrfTokenStoreInterface $tokenStore,
        private readonly ?CsrfRateLimiterInterface $rateLimiter = null,
        private readonly int $maxAttempts = 5,
        private readonly int $decaySeconds = 60,
        private readonly ?Closure $mismatchResponse = null,
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
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        if ($this->router->isCsrfExemptRoute($request)) {
            return $next($request);
        }

        $sessionToken = $this->tokenStore->getToken();
        $requestToken = $this->extractToken($request);

        // Reject immediately when either token is empty. hash_equals('', '') would
        // return true, silently bypassing CSRF protection for tokenless requests.
        if ($sessionToken === '' || $requestToken === '') {
            return $this->handleTokenMismatch($request);
        }

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
     * @return ResponseInterface
     */
    private function handleTokenMismatch(RequestInterface $request): ResponseInterface
    {
        if ($this->rateLimiter === null) {
            return $this->reject($request, 403, 'CSRF token mismatch.');
        }

        $ip = $request->server('REMOTE_ADDR', 'unknown');
        $key = 'csrf_mismatch:' . (is_string($ip) ? $ip : 'unknown');

        if ($this->rateLimiter->tooManyAttempts($key, $this->maxAttempts)) {
            return $this->reject($request, 429, 'Too Many Requests.');
        }

        $this->rateLimiter->attempt($key, $this->maxAttempts, $this->decaySeconds);

        return $this->reject($request, 403, 'CSRF token mismatch.');
    }

    /**
     * Build the rejection: the configured factory's response, the JSON error
     * envelope for clients accepting JSON, or plain text.
     *
     * @param RequestInterface $request
     * @param int              $status
     * @param string           $message
     *
     * @return ResponseInterface
     */
    private function reject(RequestInterface $request, int $status, string $message): ResponseInterface
    {
        if ($this->mismatchResponse !== null) {
            return ($this->mismatchResponse)($request, $status, $message);
        }

        $accept = $request->header('accept', '');

        if (is_string($accept) && str_contains($accept, 'application/json')) {
            $json = json_encode(['error' => ['code' => $status, 'message' => $message]], JSON_THROW_ON_ERROR);

            return (new Response($json, $status))->withHeader('Content-Type', 'application/json');
        }

        return new Response($message, $status);
    }

    /**
     * Extract the CSRF token from form input `_token`, the X-CSRF-TOKEN header
     * or the X-XSRF-TOKEN header.
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

        foreach (['X-CSRF-TOKEN', 'X-XSRF-TOKEN'] as $header) {
            $fromHeader = $request->header($header);
            if (is_string($fromHeader) && $fromHeader !== '') {
                return $fromHeader;
            }
        }

        return '';
    }
}
