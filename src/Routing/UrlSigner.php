<?php

declare(strict_types=1);

namespace EzPhp\Routing;

use InvalidArgumentException;

/**
 * Class UrlSigner
 *
 * Stateless, expiring links (unsubscribe, one-click confirmations): an
 * `expires` and a `signature` query parameter, an HMAC-SHA256 over the path and
 * the other parameters. A link stays usable until it expires — tracking one-time
 * use is the application's job (see ez-php/auth's one-time tokens).
 *
 * Scheme and host are not signed, so a link survives a proxy or a host alias.
 * Parameters are signed sorted by name, in the decoded form both sides see, so
 * re-encoding or reordering by a mail client or proxy does not break a link.
 *
 * @package EzPhp\Routing
 */
final readonly class UrlSigner
{
    private const string EXPIRES = 'expires';

    private const string SIGNATURE = 'signature';

    /**
     * @param string $key Secret key (`app.url_signing_key`); rotating it invalidates all issued links.
     *
     * @throws InvalidArgumentException When the key is empty.
     */
    public function __construct(private string $key)
    {
        if ($key === '') {
            throw new InvalidArgumentException('The URL signing key must not be empty; set app.url_signing_key.');
        }
    }

    /**
     * Append the expiry and signature to a path or absolute URL, e.g. from Router::route().
     *
     * @param string   $url        Path or absolute URL, optionally with a query string.
     * @param int      $ttlSeconds Seconds the link stays valid.
     * @param int|null $now        Current Unix time; null = time().
     *
     * @return string
     * @throws InvalidArgumentException When the URL has a fragment or already carries `expires`/`signature`.
     */
    public function sign(string $url, int $ttlSeconds, ?int $now = null): string
    {
        if (str_contains($url, '#')) {
            throw new InvalidArgumentException('A signed URL cannot have a fragment.');
        }

        [$base, $queryString] = explode('?', $url, 2) + [1 => ''];
        parse_str($queryString, $query);

        if (isset($query[self::EXPIRES]) || isset($query[self::SIGNATURE])) {
            throw new InvalidArgumentException('The URL already carries an expires or signature parameter.');
        }

        $query[self::EXPIRES] = (string) (($now ?? time()) + $ttlSeconds);
        ksort($query);
        $query[self::SIGNATURE] = $this->signature((string) parse_url($base, PHP_URL_PATH), $query);

        return $base . '?' . http_build_query($query);
    }

    /**
     * Check a link: the signature matches and it has not expired.
     *
     * @param string   $url Request URI or absolute URL, with its query string.
     * @param int|null $now Current Unix time; null = time().
     *
     * @return bool
     */
    public function verify(string $url, ?int $now = null): bool
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $signature = $query[self::SIGNATURE] ?? null;
        $expires = $query[self::EXPIRES] ?? null;

        if (!is_string($signature) || !is_string($expires) || !ctype_digit($expires)) {
            return false;
        }

        unset($query[self::SIGNATURE]);
        ksort($query);

        if (!hash_equals($this->signature((string) parse_url($url, PHP_URL_PATH), $query), $signature)) {
            return false;
        }

        return ($now ?? time()) <= (int) $expires;
    }

    /**
     * @param string       $path
     * @param array<mixed> $query Sorted parameters without the signature.
     *
     * @return string
     */
    private function signature(string $path, array $query): string
    {
        return hash_hmac('sha256', $path . '?' . http_build_query($query), $this->key);
    }
}
