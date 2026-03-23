<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

/**
 * Interface CsrfRateLimiterInterface
 *
 * Minimal rate-limiter contract used by CsrfMiddleware to throttle
 * requests with invalid CSRF tokens.
 *
 * Compatible with ez-php/rate-limiter's RateLimiterInterface — any driver
 * from that package can be passed directly without an adapter.
 *
 * @package EzPhp\Middleware
 */
interface CsrfRateLimiterInterface
{
    /**
     * Record a failed CSRF attempt for the given key.
     *
     * Returns true  — attempt recorded, still within the limit.
     * Returns false — limit already reached; attempt is NOT recorded.
     *
     * @param string $key           Per-client identifier (e.g. IP address).
     * @param int    $maxAttempts   Maximum allowed failures per window.
     * @param int    $decaySeconds  Window length in seconds.
     *
     * @return bool
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool;

    /**
     * Return true if the failure count for the given key is at or above $maxAttempts.
     *
     * @param string $key
     * @param int    $maxAttempts
     *
     * @return bool
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;
}
