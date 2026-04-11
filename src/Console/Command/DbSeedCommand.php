<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;
use EzPhp\Migration\SeederRunner;

/**
 * Class DbSeedCommand
 *
 * Runs database seeders from database/seeders/.
 * Refuses to run in production unless --force is passed.
 *
 * Usage:
 *   ez db:seed                      — run all seeders
 *   ez db:seed UserSeeder.php       — run a single seeder file
 *   ez db:seed --force              — allow running in production
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class DbSeedCommand implements CommandInterface
{
    /**
     * DbSeedCommand Constructor
     *
     * @param SeederRunner $runner
     * @param string       $env    Current APP_ENV value (e.g. 'production', 'local').
     */
    public function __construct(
        private SeederRunner $runner,
        private string $env = 'local',
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'db:seed';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Run database seeders';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez db:seed [<file>] [--force]';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);

        if ($this->env === 'production' && !$input->hasFlag('force')) {
            fwrite(STDERR, "Seeders will not run in production. Use --force to override.\n");
            return 1;
        }

        $file = $input->argument(0);

        $ran = $this->runner->run($file !== '' ? $file : null);

        if ($ran === []) {
            echo "No seeders found.\n";
            return 0;
        }

        foreach ($ran as $basename) {
            echo "Seeded: $basename\n";
        }

        return 0;
    }
}
