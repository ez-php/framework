<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Request;
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
 * Usage (in bootstrap or service provider):
 *   $app->middleware(CsrfMiddleware::class);
 *
 * @package EzPhp\Middleware
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
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
     * @param Router                  $router     Used to check whether the matched route
     *                                            is marked as CSRF-exempt via withoutCsrf().
     * @param CsrfTokenStoreInterface $tokenStore Backing store for the CSRF token.
     */
    public function __construct(
        private Router $router,
        private CsrfTokenStoreInterface $tokenStore,
    ) {
    }

    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
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
            return new Response('CSRF token mismatch.', 403);
        }

        return $next($request);
    }

    /**
     * Extract the CSRF token from form input `_token` or the X-CSRF-TOKEN header.
     *
     * @param Request $request
     *
     * @return string
     */
    private function extractToken(Request $request): string
    {
        $fromInput = $request->input('_token');
        if (is_string($fromInput) && $fromInput !== '') {
            return $fromInput;
        }

        $fromHeader = $request->header('X-CSRF-TOKEN');

        return is_string($fromHeader) ? $fromHeader : '';
    }
}
