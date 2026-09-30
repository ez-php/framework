<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;

/**
 * Class RouteClearCommand
 *
 * Removes the compiled route cache file created by `route:cache`.
 * After clearing, the application falls back to loading routes/web.php
 * on every request.
 *
 * Usage:
 *   ez route:clear
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final class RouteClearCommand implements CommandInterface
{
    /**
     * RouteClearCommand Constructor
     *
     * @param string $cachePath Absolute path to the route cache file.
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
        return 'route:clear';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Remove the compiled route cache file';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez route:clear\n\nDeletes bootstrap/cache/routes.php if it exists.";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        if (!file_exists($this->cachePath)) {
            Output::line('No route cache found.');

            return 0;
        }

        if (!unlink($this->cachePath)) {
            Output::error("Could not remove route cache: {$this->cachePath}", $this->errorStream);

            return 1;
        }

        Output::success('Route cache cleared.');

        return 0;
    }
}
