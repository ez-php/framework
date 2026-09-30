<?php

declare(strict_types=1);

namespace EzPhp\Maintenance;

/**
 * Class MaintenanceState
 *
 * The settings of an active maintenance window, read from the marker file once
 * per request (see MaintenanceMode::state()) instead of once per accessor.
 *
 * @package EzPhp\Maintenance
 */
final readonly class MaintenanceState
{
    /**
     * MaintenanceState Constructor
     *
     * @param int|null    $retryAfter Seconds for the `Retry-After` header; null omits it.
     * @param string|null $secret     Bypass secret; null = no bypass.
     */
    public function __construct(
        public ?int $retryAfter = null,
        public ?string $secret = null,
    ) {
    }

    /**
     * The bypass cookie value for the secret — derived from it, so the cookie is
     * useless once the secret changes or maintenance ends.
     *
     * @return string|null Null when no secret is set.
     */
    public function bypassToken(): ?string
    {
        return $this->secret === null ? null : hash_hmac('sha256', 'ez-maintenance-bypass', $this->secret);
    }
}
