<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\SignedUrlMiddleware;
use EzPhp\Routing\UrlSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * @package Tests\Middleware
 */
#[CoversClass(SignedUrlMiddleware::class)]
#[UsesClass(UrlSigner::class)]
final class SignedUrlMiddlewareTest extends TestCase
{
    private function statusFor(string $url): int
    {
        return (new SignedUrlMiddleware(new UrlSigner('secret')))
            ->handle(new Request('GET', $url), static fn (): Response => new Response('ok', 200))
            ->status();
    }

    public function test_a_valid_signature_passes(): void
    {
        $this->assertSame(200, $this->statusFor((new UrlSigner('secret'))->sign('/a?x=1', 60)));
    }

    public function test_an_unsigned_request_is_forbidden(): void
    {
        $this->assertSame(403, $this->statusFor('/a?x=1'));
    }

    public function test_an_expired_link_is_forbidden(): void
    {
        $this->assertSame(403, $this->statusFor((new UrlSigner('secret'))->sign('/a', 60, time() - 120)));
    }
}
