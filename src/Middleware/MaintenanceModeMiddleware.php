<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Maintenance\MaintenanceMode;

/**
 * Class MaintenanceModeMiddleware
 *
 * While `ez down` is active, answers every request with 503 Service Unavailable
 * (plus `Retry-After` when `--retry` was given; JSON for `wantsJson()` requests)
 * without reaching the router. Register it first in the global stack.
 *
 * Bypass: with `ez down --secret=<secret>`, visiting `/<secret>` sets an HttpOnly
 * cookie derived from the secret and redirects to `/`; requests carrying that
 * cookie pass through, so the team can check a deploy before bringing it up.
 *
 * @package EzPhp\Middleware
 */
final readonly class MaintenanceModeMiddleware implements MiddlewareInterface
{
    /**
     * Lifetime of the bypass cookie (12 hours).
     */
    private const int BYPASS_TTL = 43200;

    /**
     * MaintenanceModeMiddleware Constructor
     *
     * @param MaintenanceMode $maintenance
     */
    public function __construct(private MaintenanceMode $maintenance)
    {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        // One read of the marker file per request; state() is null while up.
        $state = $this->maintenance->state();

        if ($state === null) {
            /** @var ResponseInterface */
            return $next($request);
        }

        $token = $state->bypassToken();
        $secret = $state->secret;

        if ($token !== null) {
            $cookie = $request->cookie(MaintenanceMode::BYPASS_COOKIE);

            if (is_string($cookie) && hash_equals($token, $cookie)) {
                /** @var ResponseInterface */
                return $next($request);
            }

            $path = (string) parse_url($request->uri(), PHP_URL_PATH);

            if ($secret !== null && hash_equals('/' . $secret, $path)) {
                return (new Response('', 302))
                    ->withHeader('Location', '/')
                    ->withCookie(
                        MaintenanceMode::BYPASS_COOKIE,
                        $token,
                        self::BYPASS_TTL,
                        secure: $this->isHttps($request),
                        httpOnly: true,
                    );
            }
        }

        return $this->unavailable($request, $state->retryAfter);
    }

    /**
     * @param RequestInterface $request
     * @param int|null         $retry   Seconds for `Retry-After`; null omits the header.
     *
     * @return Response
     */
    private function unavailable(RequestInterface $request, ?int $retry): Response
    {
        $response = $request->wantsJson()
            ? (new Response('{"error":{"code":503,"message":"Service Unavailable"}}', 503))
                ->withHeader('Content-Type', 'application/json')
            : (new Response($this->page(), 503))
                ->withHeader('Content-Type', 'text/html; charset=utf-8');

        return $retry === null ? $response : $response->withHeader('Retry-After', (string) $retry);
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool
     */
    private function isHttps(RequestInterface $request): bool
    {
        $https = $request->server('HTTPS');

        return is_string($https) && $https !== '' && strtolower($https) !== 'off';
    }

    /**
     * @return string
     */
    private function page(): string
    {
        return <<<'HTML'
            <!DOCTYPE html>
            <html lang="en">
            <head><meta charset="UTF-8"><title>503 Service Unavailable</title></head>
            <body style="font-family: system-ui, sans-serif; text-align: center; padding: 48px;">
                <h1>Be right back.</h1>
                <p>We are performing scheduled maintenance. Please try again shortly.</p>
            </body>
            </html>
            HTML;
    }
}
