<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Migration\Migrator;
use Throwable;

/**
 * Class MigrateRollbackCommand
 *
 * @package EzPhp\Console\Command
 */
final readonly class MigrateRollbackCommand implements CommandInterface
{
    /**
     * MigrateRollbackCommand Constructor
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
        return 'migrate:rollback';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Roll back the last batch of migrations';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez migrate:rollback';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     * @throws Throwable
     */
    public function handle(array $args): int
    {
        $rolled = $this->migrator->rollback();

        if ($rolled === []) {
            echo "Nothing to roll back.\n";
            return 0;
        }

        foreach ($rolled as $file) {
            echo "Rolled back: $file\n";
        }

        return 0;
    }
}
