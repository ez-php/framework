<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Headers;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use InvalidArgumentException;

/**
 * Class CorsMiddleware
 *
 * Adds CORS response headers and answers preflight requests.
 *
 * `$allowOrigin` is `*`, a single origin (sent as-is), or a comma-separated
 * allow-list — then the request's `Origin` is echoed back only when it is listed,
 * and `Vary: Origin` tells caches the header differs per origin. A preflight is an
 * `OPTIONS` request carrying `Access-Control-Request-Method`; it is answered with
 * 204 without reaching the router. Any other `OPTIONS` request is routed normally.
 *
 * @package EzPhp\Middleware
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    /**
     * CorsMiddleware Constructor
     *
     * @param string $allowOrigin      `*`, one origin, or a comma-separated allow-list of origins.
     * @param string $allowMethods
     * @param string $allowHeaders
     * @param int    $maxAge
     * @param bool   $enabled          Set to false to disable CORS header injection without
     *                                 removing the middleware from the stack. Useful when
     *                                 CORS is toggled via configuration without code changes.
     * @param bool   $allowCredentials Send `Access-Control-Allow-Credentials: true` (cookies,
     *                                 Authorization). Not allowed together with `*`.
     *
     * @throws InvalidArgumentException When credentials are combined with the `*` origin.
     */
    public function __construct(
        private string $allowOrigin = '*',
        private string $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private string $allowHeaders = 'Content-Type, Authorization, X-Requested-With',
        private int $maxAge = 86400,
        private bool $enabled = true,
        private bool $allowCredentials = false,
    ) {
        if ($allowCredentials && trim($allowOrigin) === '*') {
            throw new InvalidArgumentException(
                'CORS credentials cannot be allowed for the wildcard origin "*" — list the allowed origins explicitly.'
            );
        }
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        if (!$this->enabled) {
            /** @var ResponseInterface $response */
            $response = $next($request);

            return $response;
        }

        if ($this->isPreflight($request)) {
            return $this->addCorsHeaders(new Response('', 204), $request);
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        return $this->addCorsHeaders($response, $request);
    }

    /**
     * @param RequestInterface $request
     *
     * @return bool
     */
    private function isPreflight(RequestInterface $request): bool
    {
        $requestedMethod = $request->header('Access-Control-Request-Method');

        return $request->method() === 'OPTIONS' && is_string($requestedMethod) && $requestedMethod !== '';
    }

    /**
     * @param ResponseInterface $response
     * @param RequestInterface  $request
     *
     * @return ResponseInterface
     */
    private function addCorsHeaders(ResponseInterface $response, RequestInterface $request): ResponseInterface
    {
        $allowed = $this->allowedOrigins();

        if (count($allowed) > 1) {
            $response = $this->varyOnOrigin($response);
            $origin = $request->header('Origin');

            if (!is_string($origin) || !in_array($origin, $allowed, true)) {
                return $response;
            }
        } else {
            $origin = $this->allowOrigin;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Add `Origin` to the response's `Vary` header, keeping whatever it already varies on.
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function varyOnOrigin(ResponseInterface $response): ResponseInterface
    {
        $vary = Headers::get($response->headers(), 'Vary');

        if ($vary === null || trim($vary) === '') {
            return $response->withHeader('Vary', 'Origin');
        }

        $fields = array_map(static fn (string $field): string => strtolower(trim($field)), explode(',', $vary));

        if (in_array('origin', $fields, true) || in_array('*', $fields, true)) {
            return $response;
        }

        return $response->withHeader('Vary', $vary . ', Origin');
    }

    /**
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $this->allowOrigin)),
            static fn (string $origin): bool => $origin !== '',
        ));
    }
}
