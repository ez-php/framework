<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Http\Headers;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;

/**
 * Class SecurityHeadersMiddleware
 *
 * Adds browser security headers to every response. Defaults: `nosniff`,
 * `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`
 * and one year of HSTS. A Content-Security-Policy is sent only when configured —
 * a generic default would break most pages.
 *
 * Overrides, in increasing precedence: the defaults, `security.headers` from
 * config, the constructor's `$headers`. A null value removes a header; names are
 * case-insensitive. A header the response already carries is left alone, so a
 * single route can relax e.g. X-Frame-Options for itself.
 *
 * Registered by class name it is autowired: `$headers` is empty and the config
 * comes from the container, so `$app->middleware(SecurityHeadersMiddleware::class)`
 * plus `config/security.php` is all the setup needed.
 *
 * @package EzPhp\Middleware
 */
final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, string>
     */
    public const array DEFAULTS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    ];

    /**
     * The headers to add, after merging defaults, config and constructor values.
     *
     * @var array<string, string>
     */
    private array $headers;

    /**
     * SecurityHeadersMiddleware Constructor
     *
     * @param array<string, string|null> $headers Overrides; null removes a header.
     * @param ConfigInterface|null       $config  Source of `security.headers` overrides.
     */
    public function __construct(array $headers = [], ?ConfigInterface $config = null)
    {
        $merged = self::DEFAULTS;

        foreach ([self::configHeaders($config), $headers] as $overrides) {
            foreach ($overrides as $name => $value) {
                $merged = self::without($merged, $name);

                if ($value !== null) {
                    $merged[$name] = $value;
                }
            }
        }

        $this->headers = $merged;
    }

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

        foreach ($this->headers as $name => $value) {
            if (Headers::get($response->headers(), $name) === null) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    /**
     * `security.headers` as a name → value|null map; anything malformed is ignored.
     *
     * @param ConfigInterface|null $config
     *
     * @return array<string, string|null>
     */
    private static function configHeaders(?ConfigInterface $config): array
    {
        $raw = $config?->get('security.headers', []);

        if (!is_array($raw)) {
            return [];
        }

        $headers = [];

        foreach ($raw as $name => $value) {
            if (is_string($name) && ($value === null || is_string($value))) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @param string                $name
     *
     * @return array<string, string>
     */
    private static function without(array $headers, string $name): array
    {
        foreach (array_keys($headers) as $existing) {
            if (strcasecmp($existing, $name) === 0) {
                unset($headers[$existing]);
            }
        }

        return $headers;
    }
}
