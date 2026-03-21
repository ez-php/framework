<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

/**
 * Class SessionCsrfTokenStore
 *
 * Persists the CSRF token in PHP's native session ($_SESSION).
 * Starts the session automatically when it has not yet been started.
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
     */
    public function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::TOKEN_KEY]) || !is_string($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::TOKEN_KEY];
    }
}
