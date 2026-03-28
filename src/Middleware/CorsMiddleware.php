<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;

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
     */
    public function __construct(
        private string $allowOrigin = '*',
        private string $allowMethods = 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        private string $allowHeaders = 'Content-Type, Authorization, X-Requested-With',
        private int $maxAge = 86400,
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
        if ($request->method() === 'OPTIONS') {
            return $this->addCorsHeaders(new Response('', 204));
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->addCorsHeaders($response);
    }

    /**
     * @param Response $response
     *
     * @return Response
     */
    private function addCorsHeaders(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', $this->allowMethods)
            ->withHeader('Access-Control-Allow-Headers', $this->allowHeaders)
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
    }
}
