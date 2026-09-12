<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;

/**
 * Class CorsMiddleware
 *
 * @package EzPhp\Middleware
 */
final readonly class CorsMiddleware implements MiddlewareInterface
{
    /**
     * CorsMiddleware Constructor
     *
     * @param string $allowOrigin
     * @param string $allowMethods
     * @param string $allowHeaders
     * @param int    $maxAge
     * @param bool   $enabled     Set to false to disable CORS header injection without
     *                            removing the middleware from the stack. Useful when
     *                            CORS is toggled via configuration without code changes.
     */
    public function __construct(
        private string $allowOrigin = '*',
        private string $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private string $allowHeaders = 'Content-Type, Authorization, X-Requested-With',
        private int $maxAge = 86400,
        private bool $enabled = true,
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
        if (!$this->enabled) {
            /** @var ResponseInterface $response */
            $response = $next($request);

            return $response;
        }

        if ($request->method() === 'OPTIONS') {
            return $this->addCorsHeaders(new Response('', 204));
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        return $this->addCorsHeaders($response);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    private function addCorsHeaders(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
    }
}
