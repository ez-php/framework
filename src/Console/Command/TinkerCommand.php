<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Application\Application;
use EzPhp\Console\CommandInterface;

/**
 * Class TinkerCommand
 *
 * Bootstraps the application and opens an interactive PsySH REPL with $app pre-set.
 * Requires psy/psysh to be installed (composer require --dev psy/psysh).
 *
 * @package EzPhp\Console\Command
 */
final class TinkerCommand implements CommandInterface
{
    /**
     * TinkerCommand Constructor
     *
     * @param Application $app The bootstrapped application instance.
     */
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'tinker';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Open an interactive REPL with the application bootstrapped (requires psy/psysh)';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez tinker\n\nRequires psy/psysh:\n  composer require --dev psy/psysh";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        if (!class_exists(\Psy\Shell::class)) {
            fwrite(STDERR, "Tinker requires PsySH. Install it with:\n");
            fwrite(STDERR, "  composer require --dev psy/psysh\n");
            return 1;
        }

        \Psy\Shell::debug(['app' => $this->app]);

        return 0;
    }
}
