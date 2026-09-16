<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Contracts\ConfigInterface;

/**
 * Class ConfigShowCommand
 *
 * Prints the resolved value of a single dot-notation config key.
 *
 * Usage:
 *   ez config:show app.debug
 *   ez config:show db
 *
 * A sentinel default is used to distinguish "key resolves to null" from
 * "key does not exist" — ConfigInterface::get() has no has()-style probe.
 * Array values are printed as pretty-printed JSON; scalars via var_export().
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final class ConfigShowCommand implements CommandInterface
{
    private const string MISSING = "\0__ez_php_config_show_missing__\0";

    /**
     * ConfigShowCommand Constructor
     *
     * @param ConfigInterface $config
     */
    public function __construct(
        private readonly ConfigInterface $config,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'config:show';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Print the resolved value of a single config key';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez config:show <key>\n\n"
            . "Prints the resolved value of a dot-notation config key (e.g. 'app.debug').\n"
            . 'Array values are printed as JSON; scalars are printed directly.';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $key = $args[0] ?? null;

        if ($key === null || $key === '') {
            Output::error('Missing key. Usage: ez config:show <key>');

            return 1;
        }

        $value = $this->config->get($key, self::MISSING);

        if ($value === self::MISSING) {
            Output::error("Config key not found: $key");

            return 1;
        }

        Output::line(is_array($value) ? (string) json_encode($value, JSON_PRETTY_PRINT) : $this->stringify($value));

        return 0;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => var_export($value, true),
        };
    }
}
