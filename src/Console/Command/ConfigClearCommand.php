<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;

/**
 * Class ConfigClearCommand
 *
 * Removes the compiled configuration cache file created by `config:cache`.
 * After clearing, the application falls back to loading individual PHP config
 * files from the `config/` directory on every request.
 *
 * Usage:
 *   ez config:clear
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final class ConfigClearCommand implements CommandInterface
{
    /**
     * ConfigClearCommand Constructor
     *
     * @param string $cachePath Absolute path to the config cache file.
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private readonly string $cachePath,
        private readonly mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'config:clear';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Remove the compiled configuration cache file';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez config:clear\n\nDeletes bootstrap/cache/config.php if it exists.";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        if (!file_exists($this->cachePath)) {
            Output::line('No configuration cache found.');

            return 0;
        }

        if (!unlink($this->cachePath)) {
            Output::error("Could not remove cache file: {$this->cachePath}", $this->errorStream);

            return 1;
        }

        Output::success('Configuration cache cleared.');

        return 0;
    }
}
