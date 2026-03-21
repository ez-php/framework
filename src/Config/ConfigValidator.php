<?php

declare(strict_types=1);

namespace EzPhp\Config;

use EzPhp\Exceptions\ConfigException;

/**
 * Class ConfigValidator
 *
 * Utility for validating configuration values at boot time.
 * Service providers call assertValidValue() in their boot() method to detect
 * misconfigured drivers or obviously wrong settings before the first request.
 *
 * Example usage in a ServiceProvider::boot():
 *
 *   ConfigValidator::assertValidValue(
 *       'cache.driver',
 *       $this->app->make(Config::class)->string('cache.driver'),
 *       ['array', 'file', 'redis'],
 *   );
 *
 * @package EzPhp\Config
 */
final class ConfigValidator
{
    /**
     * Assert that a config value is one of the allowed values.
     *
     * @param string       $key          Dot-notation config key (for the error message).
     * @param string       $value        The actual config value to check.
     * @param list<string> $validValues  Allowed values.
     *
     * @return void
     * @throws ConfigException When the value is not in the allowed list.
     */
    public static function assertValidValue(string $key, string $value, array $validValues): void
    {
        if (!in_array($value, $validValues, true)) {
            throw new ConfigException(
                "Invalid value '$value' for config key '$key'. " .
                'Allowed values: ' . implode(', ', $validValues) . '.',
            );
        }
    }

    /**
     * Assert that a config value is not empty.
     *
     * @param string $key   Dot-notation config key (for the error message).
     * @param string $value The actual config value to check.
     *
     * @return void
     * @throws ConfigException When the value is empty.
     */
    public static function assertNotEmpty(string $key, string $value): void
    {
        if ($value === '') {
            throw new ConfigException(
                "Config key '$key' must not be empty.",
            );
        }
    }
}
