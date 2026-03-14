<?php

declare(strict_types=1);

namespace EzPhp\Config;

/**
 * Class Config
 *
 * @package EzPhp\Config
 */
final readonly class Config
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
}
