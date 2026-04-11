<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;
use EzPhp\Console\Prompt;
use EzPhp\Migration\Migrator;
use EzPhp\Migration\SeederRunner;
use Throwable;

/**
 * Class DbSetupCommand
 *
 * Runs all pending migrations and then all seeders in one step.
 * Refuses to seed in production unless --force is passed.
 *
 * Usage:
 *   ez db:setup                — migrate + seed
 *   ez db:setup --refresh      — rollback all, re-migrate, seed
 *   ez db:setup --refresh-hard — drop all tables, migrate, seed (no rollback)
 *   ez db:setup --force        — allow seeding in production
 *
 * When --refresh fails, the user is asked interactively whether to fall back
 * to --refresh-hard.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class DbSetupCommand implements CommandInterface
{
    /**
     * DbSetupCommand Constructor
     *
     * @param Migrator     $migrator
     * @param SeederRunner $seederRunner
     * @param string       $env           Current APP_ENV value (e.g. 'production', 'local').
     * @param Prompt       $prompt        Interactive prompt (inject for testing).
     */
    public function __construct(
        private Migrator $migrator,
        private SeederRunner $seederRunner,
        private string $env = 'local',
        private Prompt $prompt = new Prompt(),
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'db:setup';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Run all pending migrations and seeders';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez db:setup [--refresh] [--refresh-hard] [--force]';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     * @throws Throwable
     */
    public function handle(array $args): int
    {
        $input = new Input($args);

        if ($input->hasFlag('refresh-hard')) {
            $this->dropAllTables();
        } elseif ($input->hasFlag('refresh')) {
            if (!$this->rollbackAll()) {
                return 1;
            }
        }

        $ran = $this->migrator->migrate();

        if ($ran === []) {
            echo "Nothing to migrate.\n";
        } else {
            foreach ($ran as $file) {
                echo "Migrated: $file\n";
            }
        }

        if ($this->env === 'production' && !$input->hasFlag('force')) {
            fwrite(STDERR, "Seeders will not run in production. Use --force to override.\n");
            return 1;
        }

        $seeded = $this->seederRunner->run();

        if ($seeded === []) {
            echo "No seeders found.\n";
        } else {
            foreach ($seeded as $basename) {
                echo "Seeded: $basename\n";
            }
        }

        return 0;
    }

    /**
     * Drop all tables and print each dropped table name.
     *
     * @return void
     */
    private function dropAllTables(): void
    {
        $dropped = $this->migrator->dropAllTables();

        foreach ($dropped as $table) {
            echo "Dropped: $table\n";
        }
    }

    /**
     * Roll back all migrations, printing each file.
     * On failure, prompt the user to fall back to hard refresh.
     * Returns false when the rollback failed and the user declined hard refresh.
     *
     * @return bool
     * @throws Throwable
     */
    private function rollbackAll(): bool
    {
        try {
            $rolled = $this->migrator->rollbackAll();
            foreach ($rolled as $file) {
                echo "Rolled back: $file\n";
            }
            return true;
        } catch (Throwable $e) {
            fwrite(STDERR, 'Rollback failed: ' . $e->getMessage() . "\n");

            if (!$this->prompt->confirm('Rollback failed. Drop all tables instead?')) {
                return false;
            }

            $this->dropAllTables();
            return true;
        }
    }
}
