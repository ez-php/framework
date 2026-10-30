<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Routing\UrlSigner;

/**
 * Class SignedUrlMiddleware
 *
 * Route middleware that answers 403 unless the request carries a valid,
 * unexpired signature from UrlSigner. Register it explicitly:
 *
 *   $app->middlewareAlias('signed', SignedUrlMiddleware::class);
 *   $router->get('/unsubscribe/{id}', $handler)->middleware('signed');
 *
 * @package EzPhp\Middleware
 */
final readonly class SignedUrlMiddleware implements MiddlewareInterface
{
    /**
     * @param UrlSigner $signer
     */
    public function __construct(private UrlSigner $signer)
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
        if (!$this->signer->verify($request->uri())) {
            return (new Response('Invalid or expired signature.', 403))
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }

        /** @var ResponseInterface */
        return $next($request);
    }
}
