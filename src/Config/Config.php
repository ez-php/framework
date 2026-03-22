<?php

declare(strict_types=1);

namespace EzPhp\Config;

use EzPhp\Contracts\ConfigInterface;

/**
 * Class Config
 *
 * Immutable after construction — the readonly property and final class keyword prevent mutation.
 *
 * @package EzPhp\Config
 */
final readonly class Config implements ConfigInterface
{
    /**
     * Config Constructor
     *
     * @param array<string, mixed> $config
     */
    public function __construct(
        private array $config,
    ) {
        //
    }

    /**
     * Retrieve a config value by dot-notation key, or $default when the key is absent.
     *
     * Missing key convention: returns $default (null by default) when any segment of
     * the dot-path does not exist. Never throws.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Retrieve a config value cast to string.
     *
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }
        return (string) $value;
    }

    /**
     * Retrieve a config value cast to int.
     *
     * @param string $key
     * @param int    $default
     *
     * @return int
     */
    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);
        if (!is_scalar($value) && $value !== null) {
            return $default;
        }
        return (int) $value;
    }

    /**
     * Retrieve a config value cast to bool.
     * Handles string values: 'true'/'1' → true, 'false'/'0' → false.
     *
     * @param string $key
     * @param bool   $default
     *
     * @return bool
     */
    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => (bool) $value,
            };
        }

        return (bool) $value;
    }
}
