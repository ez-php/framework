<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

/**
 * Class SessionCsrfTokenStore
 *
 * Persists the CSRF token in PHP's native session ($_SESSION).
 * The session must be started by the application before this store is used
 * (e.g. via a session-start middleware that runs before CsrfMiddleware).
 *
 * @package EzPhp\Middleware
 */
final class SessionCsrfTokenStore implements CsrfTokenStoreInterface
{
    private const TOKEN_KEY = '_csrf_token';

    /**
     * Return the CSRF token stored in the session, generating a new one if absent.
     *
     * @return string
     * @throws \RuntimeException When the session has not been started yet.
     */
    public function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException(
                'Session is not active. Start the session before CsrfMiddleware runs ' .
                '(e.g. add a session-start middleware earlier in the pipeline).'
            );
        }

        if (!isset($_SESSION[self::TOKEN_KEY]) || !is_string($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_KEY];
    }
}
