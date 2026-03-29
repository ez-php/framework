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
            Output::line('No migration files found.');
            return 0;
        }

        $tableRows = [];

        foreach ($rows as $row) {
            $status = $row['status'] === 'Ran'
                ? Output::colorize('Ran', 32)
                : Output::colorize('Pending', 33);

            $tableRows[] = [
                $status,
                $row['migration'],
                $row['batch'] !== null ? (string) $row['batch'] : '-',
            ];
        }

        Output::table(['Status', 'Migration', 'Batch'], $tableRows);

        return 0;
    }
}
