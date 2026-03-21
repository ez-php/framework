<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\HttpException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class HttpExceptionTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(HttpException::class)]
final class HttpExceptionTest extends TestCase
{
    /**
     * @return void
     */
    public function test_get_status_code_returns_given_code(): void
    {
        $e = new HttpException(403);
        $this->assertSame(403, $e->getStatusCode());
    }

    /**
     * @return void
     */
    public function test_message_is_set_correctly(): void
    {
        $e = new HttpException(422, 'Unprocessable Entity');
        $this->assertSame('Unprocessable Entity', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_empty_message_by_default(): void
    {
        $e = new HttpException(404);
        $this->assertSame('', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_get_code_equals_status_code(): void
    {
        $e = new HttpException(401);
        $this->assertSame(401, $e->getCode());
    }
}
