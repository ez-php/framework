<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Http\Headers;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;
use EzPhp\I18n\Translator;

/**
 * Class LocaleNegotiationMiddleware
 *
 * Picks the translator locale from the `Accept-Language` header (RFC 9110
 * §12.5.4): languages in q-value order, `q=0` excluded; a tag matches a
 * supported locale exactly (`de-AT` → `de_AT`, case-insensitive) or by its
 * primary language (`de-CH` → `de`). Without a match the configured locale
 * stays. The response gets `Content-Language` and `Vary: Accept-Language`, so
 * caches keep one copy per language.
 *
 * Supported locales: `app.locales`; when unset, `app.locale` plus the fallback
 * chain (`app.fallback_locales` / `app.fallback_locale`). No URL prefix or
 * cookie persistence — a chosen locale is the request's, not the user's.
 *
 * @package EzPhp\Middleware
 */
final readonly class LocaleNegotiationMiddleware implements MiddlewareInterface
{
    /**
     * @param Translator      $translator
     * @param ConfigInterface $config
     */
    public function __construct(
        private Translator $translator,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $header = $request->header('accept-language');
        $locale = is_string($header) ? $this->negotiate($header, $this->supportedLocales()) : null;

        if ($locale !== null) {
            $this->translator->setLocale($locale);
        }

        /** @var ResponseInterface $response */
        $response = $next($request);

        if (Headers::get($response->headers(), 'Content-Language') === null) {
            $response = $response->withHeader('Content-Language', str_replace('_', '-', $this->translator->getLocale()));
        }

        $vary = Headers::get($response->headers(), 'Vary');

        if ($vary === null) {
            return $response->withHeader('Vary', 'Accept-Language');
        }

        return stripos($vary, 'accept-language') === false
            ? $response->withHeader('Vary', $vary . ', Accept-Language')
            : $response;
    }

    /**
     * @param string       $header
     * @param list<string> $supported
     *
     * @return string|null The supported locale to use, or null for no match.
     */
    private function negotiate(string $header, array $supported): ?string
    {
        $byKey = [];

        foreach ($supported as $locale) {
            $byKey[strtolower(str_replace('-', '_', $locale))] = $locale;
        }

        foreach (self::ranges($header) as $range) {
            $key = strtolower(str_replace('-', '_', $range));

            if (isset($byKey[$key])) {
                return $byKey[$key];
            }

            $primary = explode('_', $key)[0];

            if (isset($byKey[$primary])) {
                return $byKey[$primary];
            }
        }

        return null;
    }

    /**
     * Language ranges of the header, highest q first; `*` and q=0 dropped.
     *
     * @param string $header
     *
     * @return list<string>
     */
    private static function ranges(string $header): array
    {
        $ranges = [];

        foreach (explode(',', $header) as $position => $part) {
            $pieces = array_map('trim', explode(';', $part));
            $range = $pieces[0];
            $quality = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^q=([01](?:\.\d{0,3})?)$/i', $parameter, $m) === 1) {
                    $quality = (float) $m[1];
                }
            }

            if ($quality > 0.0 && preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8})*$/', $range) === 1) {
                $ranges[] = ['range' => $range, 'q' => $quality, 'position' => $position];
            }
        }

        usort($ranges, static fn (array $a, array $b): int => [$b['q'], $a['position']] <=> [$a['q'], $b['position']]);

        return array_column($ranges, 'range');
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        $locales = $this->config->get('app.locales');

        if (is_array($locales) && $locales !== []) {
            return array_values(array_filter($locales, 'is_string'));
        }

        $candidates = [$this->config->get('app.locale', $this->translator->getLocale())];
        $chain = $this->config->get('app.fallback_locales');

        if (is_array($chain) && $chain !== []) {
            array_push($candidates, ...array_values($chain));
        } else {
            $candidates[] = $this->config->get('app.fallback_locale');
        }

        return array_values(array_unique(array_filter($candidates, static fn (mixed $l): bool => is_string($l) && $l !== '')));
    }
}
