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

    /**
     * Original [Controller::class, 'method'] array handler, preserved for
     * route caching. Set by Router when an array handler is registered.
     * Null for closure-based routes, which cannot be serialized.
     *
     * @var array{0: class-string, 1: string}|null
     */
    private ?array $rawHandler = null;

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
        if (@preg_match('#' . $pattern . '#', '') === false) {
            throw new \InvalidArgumentException(
                "Invalid regex pattern for route parameter '$param': $pattern"
            );
        }

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
     * @internal Called by MiddlewareHandler; not part of the public route API.
     *
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
     * Store the original [Controller::class, 'method'] handler for caching.
     *
     * @internal Called by Router immediately after route registration.
     *
     * @param array{0: class-string, 1: string} $handler
     *
     * @return $this
     */
    public function withRawHandler(array $handler): self
    {
        $this->rawHandler = $handler;

        return $this;
    }

    /**
     * Return the original array handler used for route caching, or null for
     * closure-based routes that cannot be serialized.
     *
     * @return array{0: class-string, 1: string}|null
     */
    public function getRawHandler(): ?array
    {
        return $this->rawHandler;
    }

    /**
     * Return all per-parameter regex constraints registered via where().
     *
     * @return array<string, string>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
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
     * @internal Called by Router after model binding; not part of the public route API.
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
     * @internal Called by Router during dispatch; not part of the public route API.
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
     * @internal Called by Router::route(); use $router->route(name, params) instead.
     *
     * Generate a URL for this route by substituting the given parameter values.
     *
     * Optional parameters ({param?}) that are not provided — together with any
     * literal segments that immediately precede them and have become "dangling"
     * (i.e. all segments to their right are removed) — are stripped from the
     * generated URL from right to left.
     *
     * A literal is only stripped when:
     *   (a) every segment to its right has already been removed, AND
     *   (b) there is at least one optional segment to its left (filled or not).
     *
     * Condition (b) prevents base-path literals (e.g. /users in /users/{id?})
     * from being removed when no parameters are provided at all; only literals
     * that sit between optional parameters can ever be stripped.
     *
     * @param array<string, string> $params
     *
     * @return string
     */
    public function generateUrl(array $params): string
    {
        $rawSegments = explode('/', $this->path);
        $n = count($rawSegments);

        // Track which segments are optional and whether they are filled.
        $isOptional = array_fill(0, $n, false);
        $removed = array_fill(0, $n, false);

        foreach ($rawSegments as $i => $token) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}$/', $token, $m)) {
                $isOptional[$i] = true;
                if (!array_key_exists($m[1], $params)) {
                    $removed[$i] = true;
                }
            }
        }

        // Propagate removal to dangling literals (right-to-left).
        for ($i = $n - 1; $i >= 0; $i--) {
            if ($isOptional[$i] || preg_match('/^\{[a-zA-Z_][a-zA-Z0-9_]*\}$/', $rawSegments[$i])) {
                // Optional params were handled above; required params are never removed.
                continue;
            }

            // Only process literal segments.
            $allRightRemoved = true;
            for ($j = $i + 1; $j < $n; $j++) {
                if (!$removed[$j]) {
                    $allRightRemoved = false;
                    break;
                }
            }

            if (!$allRightRemoved) {
                continue;
            }

            // Remove this literal only when there is any optional to its left.
            // A literal between two optional params (e.g. /posts in /users/{id?}/posts/{slug?})
            // becomes dangling when all optionals to its right are removed.
            for ($j = 0; $j < $i; $j++) {
                if ($isOptional[$j]) {
                    $removed[$i] = true;
                    break;
                }
            }
        }

        // Build the URL from all non-removed segments.
        $parts = [];
        foreach ($rawSegments as $i => $token) {
            if ($removed[$i]) {
                continue;
            }

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\?\}$/', $token, $m)) {
                $parts[] = (string) ($params[$m[1]] ?? '');
            } elseif (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $token, $m)) {
                $parts[] = (string) ($params[$m[1]] ?? $token);
            } else {
                $parts[] = $token;
            }
        }

        $url = implode('/', $parts);

        return $url !== '' ? $url : '/';
    }

    /**
     * @internal Called by MiddlewareHandler; not part of the public route API.
     *
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
