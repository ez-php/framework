<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;

/**
 * Class DebugToolbarMiddleware
 *
 * Injects a lightweight debug toolbar into HTML responses when the application
 * is running in debug mode (APP_DEBUG=true). The toolbar is appended just
 * before the closing </body> tag and shows:
 *   - HTTP method and URI
 *   - Response status code
 *   - Wall-clock execution time (ms)
 *   - Peak memory usage (MB)
 *
 * Registration is handled automatically by ExceptionHandlerServiceProvider
 * when the debug flag is enabled. No application code needs to register it.
 *
 * The toolbar is entirely self-contained (inline CSS + HTML) and adds no
 * external dependencies or runtime overhead in production.
 *
 * @package EzPhp\Middleware
 */
final class DebugToolbarMiddleware implements MiddlewareInterface
{
    private float $startTime;

    /**
     * DebugToolbarMiddleware Constructor
     */
    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        $this->startTime = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $body = $response->body();

        // Only inject into HTML-like responses that contain a closing body or html tag
        if (!str_contains($body, '</body>') && !str_contains($body, '</html>')) {
            return $response;
        }

        // Skip if the Content-Type header signals a non-HTML format
        $contentType = $response->headers()['Content-Type'] ?? '';
        if ($contentType !== '' && !str_contains($contentType, 'text/html')) {
            return $response;
        }

        $toolbar = $this->renderToolbar($request, $response);

        if (str_contains($body, '</body>')) {
            $body = str_replace('</body>', $toolbar . '</body>', $body);
        } else {
            $body = str_replace('</html>', $toolbar . '</html>', $body);
        }

        $new = new Response($body, $response->status());
        foreach ($response->headers() as $name => $value) {
            $new = $new->withHeader($name, $value);
        }
        foreach ($response->cookies() as $cookie) {
            $new = $new->withCookie(
                $cookie->name(),
                $cookie->value(),
                $cookie->ttl(),
                $cookie->path(),
                $cookie->domain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
            );
        }

        return $new;
    }

    /**
     * Render the toolbar HTML snippet.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return string
     */
    private function renderToolbar(Request $request, Response $response): string
    {
        $elapsed = round((microtime(true) - $this->startTime) * 1000, 2);
        $memory = round(memory_get_peak_usage(true) / 1_048_576, 2);
        $method = htmlspecialchars($request->method(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $uri = htmlspecialchars($request->uri(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $status = $response->status();

        $statusColor = match (true) {
            $status >= 500 => '#e74c3c',
            $status >= 400 => '#e67e22',
            $status >= 300 => '#3498db',
            default => '#27ae60',
        };

        return <<<HTML
            <div id="ez-debug-toolbar" style="
                position:fixed;bottom:0;left:0;right:0;z-index:99999;
                background:#1a1a2e;color:#eee;font:13px/1 monospace;
                display:flex;align-items:center;gap:16px;padding:6px 14px;
                box-shadow:0 -2px 8px rgba(0,0,0,.4);
            ">
                <span style="color:#7f8c8d;font-size:11px;font-weight:bold;letter-spacing:.05em;">ez-php</span>
                <span style="background:$statusColor;color:#fff;padding:2px 7px;border-radius:3px;font-weight:bold;">$status</span>
                <span><b style="color:#aaa;">$method</b> $uri</span>
                <span style="margin-left:auto;color:#aaa;">&#128336; <b style="color:#f1c40f;">{$elapsed}ms</b></span>
                <span style="color:#aaa;">&#129504; <b style="color:#9b59b6;">{$memory}MB</b></span>
            </div>
            HTML;
    }
}
