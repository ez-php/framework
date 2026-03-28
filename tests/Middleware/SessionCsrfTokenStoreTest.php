<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Middleware\SessionCsrfTokenStore;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class SessionCsrfTokenStoreTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(SessionCsrfTokenStore::class)]
final class SessionCsrfTokenStoreTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * @return void
     */
    public function test_getToken_throws_when_session_not_active(): void
    {
        $this->assertSame(PHP_SESSION_NONE, session_status());

        $store = new SessionCsrfTokenStore();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session is not active.');

        $store->getToken();
    }

    /**
     * @return void
     */
    public function test_getToken_returns_string_when_session_active(): void
    {
        session_start();

        $store = new SessionCsrfTokenStore();
        $token = $store->getToken();

        $this->assertNotEmpty($token);
    }

    /**
     * @return void
     */
    public function test_getToken_returns_same_token_on_subsequent_calls(): void
    {
        session_start();

        $store = new SessionCsrfTokenStore();
        $first = $store->getToken();
        $second = $store->getToken();

        $this->assertSame($first, $second);
    }

    /**
     * @return void
     */
    public function test_getToken_generates_hex_token(): void
    {
        session_start();

        $store = new SessionCsrfTokenStore();
        $token = $store->getToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }
}
