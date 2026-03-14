<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use Closure;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareInterface;

/**
 * Class Route
 *
 * @package EzPhp\Routing
 */
final class Route
{
    /**
     * @var array<int, class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    /**
     * @var array<string, string>
     */
    private array $params = [];

    private ?string $name = null;

    private readonly Closure $handler;

    /**
     * Route Constructor
     *
     * @param string   $method
     * @param string   $path
     * @param callable $handler
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        callable $handler
    ) {
        $this->handler = $handler(...);
    }

    /**
     * @param class-string<MiddlewareInterface> $middleware
     *
     * @return $this
     */
    public function middleware(string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * @return array<int, class-string<MiddlewareInterface>>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Returns a clone of this route with matched params populated,
     * or null if the route does not match the request.
     *
     * @param Request $request
     *
     * @return static|null
     */
    public function matches(Request $request): ?static
    {
        if ($request->method() !== $this->method) {
            return null;
        }

        $path = $this->normalizePath($this->path);
        $uri = $this->normalizePath($request->uri());
        $pattern = $this->buildPattern($path);

        if (!preg_match($pattern, $uri, $matches)) {
            return null;
        }

        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\??\}/', $this->path, $names);

        $params = [];
        foreach ($names[1] as $i => $name) {
            $value = $matches[$i + 1] ?? '';
            if ($value !== '') {
                $params[$name] = $value;
            }
        }

        return clone($this, [
            'params' => $params,
        ]);
    }

    /**
     * @param array<string, string> $params
     *
     * @return string
     */
    public function generateUrl(array $params): string
    {
        $url = $this->path;

        foreach ($params as $key => $value) {
            $quoted = preg_quote($key, '/');
            $url = (string) preg_replace('/\{' . $quoted . '\?\}/', $value, $url);
            $url = (string) preg_replace('/\{' . $quoted . '\}/', $value, $url);
        }

        // Remove any remaining optional segments that were not provided
        $url = (string) preg_replace('/\/\{[^}]+\?\}/', '', $url);

        return $url !== '' ? $url : '/';
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function run(Request $request): Response
    {
        $result = ($this->handler)($request->withParams($this->params));

        if ($result instanceof Response) {
            return $result;
        }

        return new Response((string) $result);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function normalizePath(string $path): string
    {
        return strlen($path) > 1 ? rtrim($path, '/') : $path;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function buildPattern(string $path): string
    {
        // Optional params with preceding slash: /{param?}
        $pattern = (string) preg_replace('/\/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/', '(?:\/([^/]+))?', $path);
        // Required params: {param}
        $pattern = (string) preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '([^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }
}
