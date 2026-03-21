<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

/**
 * Interface CsrfTokenStoreInterface
 *
 * Abstraction over the backing store used to persist and retrieve the CSRF
 * token across requests. Allows the SessionCsrfTokenStore to be swapped out
 * in tests or alternative session implementations.
 *
 * @package EzPhp\Middleware
 */
interface CsrfTokenStoreInterface
{
    /**
     * Return the current CSRF token, generating and persisting one if absent.
     *
     * @return string
     */
    public function getToken(): string;
}
