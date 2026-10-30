<?php

declare(strict_types=1);

namespace Tests\Routing;

use EzPhp\Routing\UrlSigner;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * @package Tests\Routing
 */
#[CoversClass(UrlSigner::class)]
final class UrlSignerTest extends TestCase
{
    private const int NOW = 1_800_000_000;

    /**
     * @return array{string, array<string, string>}
     */
    private function parts(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        /** @var array<string, string> $query */

        return [(string) parse_url($url, PHP_URL_PATH), $query];
    }

    /**
     * @param array<string, mixed> $query
     */
    private function url(string $path, array $query): string
    {
        return $path . '?' . http_build_query($query);
    }

    private function signer(string $key = 'secret'): UrlSigner
    {
        return new UrlSigner($key);
    }

    public function test_a_signed_url_verifies(): void
    {
        $url = $this->signer()->sign('/unsubscribe/7?list=news', 60, self::NOW);
        [, $query] = $this->parts($url);

        $this->assertSame('news', $query['list']);
        $this->assertSame((string) (self::NOW + 60), $query['expires']);
        $this->assertTrue($this->signer()->verify($url, self::NOW));
    }

    public function test_parameter_order_and_host_do_not_matter(): void
    {
        $url = $this->signer()->sign('https://app.test/a?b=2&a=1', 60, self::NOW);
        [$path, $query] = $this->parts($url);

        $this->assertStringStartsWith('https://app.test/a?', $url);
        $this->assertTrue($this->signer()->verify($this->url($path, array_reverse($query, true)), self::NOW));
    }

    public function test_it_expires(): void
    {
        $url = $this->signer()->sign('/a', 60, self::NOW);

        $this->assertTrue($this->signer()->verify($url, self::NOW + 60));
        $this->assertFalse($this->signer()->verify($url, self::NOW + 61));
    }

    public function test_tampering_is_rejected(): void
    {
        $url = $this->signer()->sign('/a?user=1', 60, self::NOW);
        [$path, $query] = $this->parts($url);

        $this->assertFalse($this->signer()->verify($this->url('/b', $query), self::NOW), 'path');
        $this->assertFalse($this->signer()->verify($this->url($path, ['user' => '2'] + $query), self::NOW), 'parameter');
        $this->assertFalse($this->signer()->verify($this->url($path, ['expires' => (string) (self::NOW + 999)] + $query), self::NOW), 'expiry');
        $this->assertFalse($this->signer()->verify($this->url($path, $query + ['extra' => '1']), self::NOW), 'added parameter');
        $this->assertFalse($this->signer('other')->verify($url, self::NOW), 'key');
    }

    public function test_missing_or_malformed_parameters_are_rejected(): void
    {
        [$path, $query] = $this->parts($this->signer()->sign('/a', 60, self::NOW));

        $this->assertFalse($this->signer()->verify($this->url($path, ['expires' => $query['expires']]), self::NOW), 'no signature');
        $this->assertFalse($this->signer()->verify($this->url($path, ['signature' => $query['signature']]), self::NOW), 'no expiry');
        $this->assertFalse($this->signer()->verify($this->url($path, ['expires' => ['1'], 'signature' => $query['signature']]), self::NOW), 'array expiry');
        $this->assertFalse($this->signer()->verify($this->url($path, ['expires' => $query['expires'], 'signature' => ['x']]), self::NOW), 'array signature');
    }

    public function test_an_empty_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UrlSigner('');
    }

    public function test_a_url_with_a_fragment_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->signer()->sign('/a#top', 60);
    }

    public function test_a_url_that_is_already_signed_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->signer()->sign('/a?expires=1', 60);
    }
}
