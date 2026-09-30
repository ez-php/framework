<?php

declare(strict_types=1);

namespace Tests\Middleware;

use EzPhp\Config\Config;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\I18n\Translator;
use EzPhp\Middleware\LocaleNegotiationMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class LocaleNegotiationMiddlewareTest
 *
 * @package Tests\Middleware
 */
#[CoversClass(LocaleNegotiationMiddleware::class)]
#[UsesClass(Config::class)]
final class LocaleNegotiationMiddlewareTest extends TestCase
{
    /**
     * @param array<string, mixed> $app
     *
     * @return array{string, array<string, string>} Negotiated locale and response headers.
     */
    private function negotiate(?string $acceptLanguage, array $app = ['locale' => 'en', 'locales' => ['en', 'de', 'de_AT', 'fr']]): array
    {
        $translator = new Translator('en', 'en', sys_get_temp_dir());
        $middleware = new LocaleNegotiationMiddleware($translator, new Config(['app' => $app]));
        $headers = $acceptLanguage === null ? [] : ['Accept-Language' => $acceptLanguage];

        $response = $middleware->handle(new Request('GET', '/', headers: $headers), static fn (): Response => new Response('ok'));

        return [$translator->getLocale(), array_change_key_case($response->headers())];
    }

    /**
     * @return array<string, array{string|null, string}>
     */
    public static function cases(): array
    {
        return [
            'exact' => ['fr', 'fr'],
            'region with hyphen' => ['de-AT', 'de_AT'],
            'region falls back to language' => ['de-CH', 'de'],
            'q-values order' => ['fr;q=0.4, de;q=0.9, en;q=0.5', 'de'],
            'unsupported first' => ['ja, fr;q=0.8', 'fr'],
            'case-insensitive' => ['DE-at', 'de_AT'],
            'wildcard keeps default' => ['*', 'en'],
            'q=0 excluded' => ['fr;q=0, de;q=0.1', 'de'],
            'nothing supported keeps default' => ['ja, zh-CN', 'en'],
            'no header keeps default' => [null, 'en'],
            'garbage keeps default' => [';;;q=abc', 'en'],
        ];
    }

    #[DataProvider('cases')]
    public function test_negotiation(?string $header, string $expected): void
    {
        self::assertSame($expected, $this->negotiate($header)[0]);
    }

    public function test_sets_content_language_and_vary(): void
    {
        [, $headers] = $this->negotiate('de-AT');

        self::assertSame('de-AT', $headers['content-language']);
        self::assertSame('Accept-Language', $headers['vary']);
    }

    public function test_without_app_locales_the_default_and_fallback_chain_are_supported(): void
    {
        self::assertSame('de', $this->negotiate('de, fr;q=0.9', ['locale' => 'en', 'fallback_locales' => ['de']])[0]);
        self::assertSame('en', $this->negotiate('fr', ['locale' => 'en', 'fallback_locale' => 'de'])[0]);
    }
}
