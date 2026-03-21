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
     * @var array<string, mixed>
     */
    private array $params = [];

    /**
     * @var array<string, string>
     */
    private array $constraints = [];

    private ?string $name = null;

    private bool $csrfExempt = false;

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
     * Add a regex constraint for a named route parameter.
     * When the route is matched, the parameter will only match if the segment
     * satisfies this pattern. Defaults to [^/]+ when not constrained.
     *
     * @param string $param   The route parameter name (without braces).
     * @param string $pattern A regex pattern fragment (no delimiters).
     *
     * @return $this
     */
    public function where(string $param, string $pattern): self
    {
        $this->constraints[$param] = $pattern;
        return $this;
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
     * Exclude this route from CSRF token verification.
     *
     * Useful for API endpoints or webhook receivers that are authenticated
     * via other means (e.g. HMAC signatures, Bearer tokens).
     *
     * @return $this
     */
    public function withoutCsrf(): self
    {
        $this->csrfExempt = true;

        return $this;
    }

    /**
     * Return true if CSRF verification should be skipped for this route.
     *
     * @return bool
     */
    public function isCsrfExempt(): bool
    {
        return $this->csrfExempt;
    }

    /**
     * Return the currently matched route parameters (raw strings from the URL
     * or resolved model instances after model binding is applied).
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Return a clone of this route with the given resolved params replacing the
     * current raw string params. Used by the Router after applying model bindings.
     *
     * @param array<string, mixed> $resolvedParams
     *
     * @return static
     */
    public function withResolvedParams(array $resolvedParams): static
    {
        return clone($this, [
            'params' => $resolvedParams,
        ]);
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
        $pattern = (string) preg_replace_callback(
            '/\/\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}/',
            function (array $m): string {
                $paramPattern = $this->constraints[$m[1]] ?? '[^/]+';
                return '(?:\/(' . $paramPattern . '))?';
            },
            $path
        );
        // Required params: {param}
        $pattern = (string) preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            function (array $m): string {
                $paramPattern = $this->constraints[$m[1]] ?? '[^/]+';
                return '(' . $paramPattern . ')';
            },
            $pattern
        );
        return '#^' . $pattern . '$#';
    }
}
