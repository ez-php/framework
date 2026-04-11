<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;
use EzPhp\Migration\Migrator;
use EzPhp\Migration\SeederRunner;
use Throwable;

/**
 * Class MigrateFreshCommand
 *
 * Rolls back all executed migrations and re-runs them from scratch.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MigrateFreshCommand implements CommandInterface
{
    /**
     * MigrateFreshCommand Constructor
     *
     * @param Migrator          $migrator
     * @param SeederRunner|null $seederRunner  Injected when the seeder infrastructure is available.
     */
    public function __construct(
        private Migrator $migrator,
        private ?SeederRunner $seederRunner = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'migrate:fresh';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Roll back all migrations and re-run them from scratch';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez migrate:fresh [--seed]';
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

        $rolled = $this->migrator->rollbackAll();

        foreach ($rolled as $file) {
            echo "Rolled back: $file\n";
        }

        $ran = $this->migrator->migrate();

        if ($ran === []) {
            echo "Nothing to migrate.\n";
        } else {
            foreach ($ran as $file) {
                echo "Migrated: $file\n";
            }
        }

        if ($input->hasFlag('seed') && $this->seederRunner !== null) {
            $seeded = $this->seederRunner->run();
            foreach ($seeded as $file) {
                echo "Seeded: $file\n";
            }
        }

        return 0;
    }
}
