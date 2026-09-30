<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Headers;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;

/**
 * Class ConditionalGetMiddleware
 *
 * Answers conditional GET/HEAD requests with 304 Not Modified (RFC 9110 §13):
 * a 200 string response gets a strong `ETag` (sha1 of the body) unless the
 * application set one, and a request whose `If-None-Match` matches it (weak
 * comparison, `*` included) — or, without `If-None-Match`, whose
 * `If-Modified-Since` is not older than the response's `Last-Modified` — gets an
 * empty 304 carrying the validators and caching headers.
 *
 * Left alone: other methods, non-200 responses, StreamedResponse (hashing it
 * would consume the stream), and responses that set cookies (a 304 would drop them).
 * The body is still produced — this saves bandwidth, not server work.
 *
 * @package EzPhp\Middleware
 */
final readonly class ConditionalGetMiddleware implements MiddlewareInterface
{
    /**
     * Headers a 304 repeats from the full response (RFC 9110 §15.4.5).
     */
    private const array KEPT_HEADERS = ['Cache-Control', 'Content-Location', 'Date', 'ETag', 'Expires', 'Last-Modified', 'Vary'];

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

        if (!in_array($request->method(), ['GET', 'HEAD'], true)
            || !$response instanceof Response
            || $response->status() !== 200
            || $response->cookies() !== []
        ) {
            return $response;
        }

        $etag = Headers::get($response->headers(), 'ETag');

        if ($etag === null) {
            $etag = '"' . sha1($response->body()) . '"';
            $response = $response->withHeader('ETag', $etag);
        }

        return $this->isNotModified($request, $response, $etag) ? $this->notModified($response) : $response;
    }

    /**
     * @param RequestInterface $request
     * @param Response         $response
     * @param string           $etag
     *
     * @return bool
     */
    private function isNotModified(RequestInterface $request, Response $response, string $etag): bool
    {
        $ifNoneMatch = $request->header('if-none-match');

        if (is_string($ifNoneMatch) && trim($ifNoneMatch) !== '') {
            if (trim($ifNoneMatch) === '*') {
                return true;
            }

            foreach (explode(',', $ifNoneMatch) as $candidate) {
                if (self::opaque($candidate) === self::opaque($etag)) {
                    return true;
                }
            }

            return false;
        }

        $ifModifiedSince = $request->header('if-modified-since');
        $lastModified = Headers::get($response->headers(), 'Last-Modified');

        if (!is_string($ifModifiedSince) || $lastModified === null) {
            return false;
        }

        $since = strtotime($ifModifiedSince);
        $modified = strtotime($lastModified);

        return $since !== false && $modified !== false && $modified <= $since;
    }

    /**
     * An entity tag without the weak prefix, for weak comparison.
     */
    private static function opaque(string $etag): string
    {
        $etag = trim($etag);

        return str_starts_with($etag, 'W/') ? substr($etag, 2) : $etag;
    }

    /**
     * @param Response $response
     *
     * @return Response
     */
    private function notModified(Response $response): Response
    {
        $notModified = new Response('', 304);

        foreach (self::KEPT_HEADERS as $name) {
            $value = Headers::get($response->headers(), $name);

            if ($value !== null) {
                $notModified = $notModified->withHeader($name, $value);
            }
        }

        return $notModified;
    }
}
