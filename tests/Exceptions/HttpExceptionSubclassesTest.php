<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Exceptions\BadRequestException;
use EzPhp\Exceptions\ForbiddenException;
use EzPhp\Exceptions\HttpException;
use EzPhp\Exceptions\NotFoundException;
use EzPhp\Exceptions\UnauthorizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class HttpExceptionSubclassesTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(NotFoundException::class)]
#[CoversClass(ForbiddenException::class)]
#[CoversClass(UnauthorizedException::class)]
#[CoversClass(BadRequestException::class)]
#[UsesClass(HttpException::class)]
final class HttpExceptionSubclassesTest extends TestCase
{
    /**
     * @return void
     */
    public function test_not_found_exception_has_status_404(): void
    {
        $e = new NotFoundException();
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('Not Found', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_not_found_exception_accepts_custom_message(): void
    {
        $e = new NotFoundException('Page missing');
        $this->assertSame(404, $e->getStatusCode());
        $this->assertSame('Page missing', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_forbidden_exception_has_status_403(): void
    {
        $e = new ForbiddenException();
        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame('Forbidden', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_forbidden_exception_accepts_custom_message(): void
    {
        $e = new ForbiddenException('Access denied');
        $this->assertSame(403, $e->getStatusCode());
        $this->assertSame('Access denied', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_unauthorized_exception_has_status_401(): void
    {
        $e = new UnauthorizedException();
        $this->assertSame(401, $e->getStatusCode());
        $this->assertSame('Unauthorized', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_unauthorized_exception_accepts_custom_message(): void
    {
        $e = new UnauthorizedException('Token expired');
        $this->assertSame(401, $e->getStatusCode());
        $this->assertSame('Token expired', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_bad_request_exception_has_status_400(): void
    {
        $e = new BadRequestException();
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('Bad Request', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_bad_request_exception_accepts_custom_message(): void
    {
        $e = new BadRequestException('Invalid input');
        $this->assertSame(400, $e->getStatusCode());
        $this->assertSame('Invalid input', $e->getMessage());
    }

    /**
     * @return void
     */
    public function test_all_subclasses_extend_http_exception(): void
    {
        $this->assertInstanceOf(HttpException::class, new NotFoundException());
        $this->assertInstanceOf(HttpException::class, new ForbiddenException());
        $this->assertInstanceOf(HttpException::class, new UnauthorizedException());
        $this->assertInstanceOf(HttpException::class, new BadRequestException());
    }
}
