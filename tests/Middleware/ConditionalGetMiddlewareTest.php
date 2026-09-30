<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Http\StreamedResponse;
use EzPhp\Middleware\ConditionalGetMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ConditionalGetMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(ConditionalGetMiddleware::class)]
final class ConditionalGetMiddlewareTest extends TestCase
{
    /**
     * @param array<string, mixed>              $headers
     * @param (\Closure(): ResponseInterface)|null $next
     */
    private function through(string $method = 'GET', array $headers = [], ?\Closure $next = null): ResponseInterface
    {
        return (new ConditionalGetMiddleware())->handle(
            new Request($method, '/page', headers: $headers),
            $next ?? static fn (): Response => (new Response('hello'))->withHeader('Cache-Control', 'max-age=60'),
        );
    }

    private static function etagOf(ResponseInterface $response): string
    {
        return array_change_key_case($response->headers())['etag'] ?? '';
    }

    public function test_adds_an_etag_to_a_200_response(): void
    {
        $response = $this->through();

        self::assertSame(200, $response->status());
        self::assertSame('"' . sha1('hello') . '"', self::etagOf($response));
    }

    public function test_matching_if_none_match_returns_304_without_body(): void
    {
        $etag = self::etagOf($this->through());

        $response = $this->through(headers: ['If-None-Match' => 'W/"other", ' . $etag]);

        self::assertSame(304, $response->status());
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('', $response->body());
        self::assertSame($etag, self::etagOf($response));
        self::assertSame('max-age=60', array_change_key_case($response->headers())['cache-control']);
    }

    public function test_weak_comparison_and_wildcard(): void
    {
        $etag = self::etagOf($this->through());

        self::assertSame(304, $this->through(headers: ['If-None-Match' => 'W/' . $etag])->status());
        self::assertSame(304, $this->through(headers: ['If-None-Match' => '*'])->status());
        self::assertSame(200, $this->through(headers: ['If-None-Match' => '"stale"'])->status());
    }

    public function test_head_requests_are_handled_too(): void
    {
        $etag = self::etagOf($this->through());

        self::assertSame(304, $this->through('HEAD', ['If-None-Match' => $etag])->status());
    }

    public function test_an_etag_set_by_the_application_is_kept(): void
    {
        $next = static fn (): Response => (new Response('body'))->withHeader('ETag', '"v42"');

        self::assertSame('"v42"', self::etagOf($this->through(next: $next)));
        self::assertSame(304, $this->through(headers: ['If-None-Match' => '"v42"'], next: $next)->status());
    }

    public function test_if_modified_since_against_last_modified(): void
    {
        $next = static fn (): Response => (new Response('doc'))
            ->withHeader('Last-Modified', 'Wed, 21 Oct 2026 07:28:00 GMT')
            ->withHeader('ETag', '"doc"');

        self::assertSame(304, $this->through(headers: ['If-Modified-Since' => 'Wed, 21 Oct 2026 07:28:00 GMT'], next: $next)->status());
        self::assertSame(200, $this->through(headers: ['If-Modified-Since' => 'Tue, 20 Oct 2026 07:28:00 GMT'], next: $next)->status());
        // If-None-Match takes precedence over If-Modified-Since.
        self::assertSame(200, $this->through(headers: [
            'If-None-Match' => '"changed"',
            'If-Modified-Since' => 'Wed, 21 Oct 2026 07:28:00 GMT',
        ], next: $next)->status());
    }

    public function test_leaves_other_requests_and_responses_alone(): void
    {
        $etag = self::etagOf($this->through());

        self::assertSame(200, $this->through('POST', ['If-None-Match' => $etag])->status());
        self::assertSame('', self::etagOf($this->through('POST')));

        $error = static fn (): Response => new Response('missing', 404);
        self::assertSame('', self::etagOf($this->through(next: $error)));

        $stream = static fn (): StreamedResponse => new StreamedResponse(static fn (): iterable => ['a']);
        self::assertInstanceOf(StreamedResponse::class, $this->through(headers: ['If-None-Match' => '*'], next: $stream));
    }

    public function test_a_response_setting_cookies_is_never_turned_into_a_304(): void
    {
        $next = static fn (): Response => (new Response('hello'))->withCookie('flash', '1');

        $response = $this->through(headers: ['If-None-Match' => '*'], next: $next);

        self::assertSame(200, $response->status());
        self::assertCount(1, $response->cookies());
    }
}
