<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Migration\Migrator;

/**
 * Class MigrateStatusCommand
 *
 * Shows the status of every migration file: Pending or Ran (with batch number).
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MigrateStatusCommand implements CommandInterface
{
    /**
     * MigrateStatusCommand Constructor
     *
     * @param Migrator $migrator
     */
    public function __construct(private Migrator $migrator)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'migrate:status';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Show the status of all migrations (pending / ran)';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez migrate:status';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $rows = $this->migrator->status();

        if ($rows === []) {
            echo "No migration files found.\n";
            return 0;
        }

        foreach ($rows as $row) {
            $status = $row['status'] === 'Ran'
                ? Output::colorize('Ran', 32)
                : Output::colorize('Pending', 33);

            $batch = $row['batch'] !== null ? "  (batch {$row['batch']})" : '';

            echo sprintf("  %-10s %s%s\n", $status, $row['migration'], $batch);
        }

        return 0;
    }
}
