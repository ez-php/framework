<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Migration\Migrator;
use Throwable;

/**
 * Class MigrateCommand
 *
 * @package EzPhp\Console\Command
 */
final class MigrateCommand implements CommandInterface
{
    /**
     * MigrateCommand Constructor
     *
     * @param Migrator $migrator
     */
    public function __construct(private readonly Migrator $migrator)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'migrate';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Run all pending migrations';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez migrate';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     * @throws Throwable
     */
    public function handle(array $args): int
    {
        $ran = $this->migrator->migrate();

        if ($ran === []) {
            echo "Nothing to migrate.\n";
            return 0;
        }

        foreach ($ran as $file) {
            echo "Migrated: $file\n";
        }

        return 0;
    }
}
