<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Database\Database;
use Throwable;

/**
 * Class Migrator
 *
 * @internal
 * @package EzPhp\Migration
 */
final class Migrator
{
    /**
     * @var array<string, MigrationInterface>
     */
    private array $loaded = [];

    /**
     * Migrator Constructor
     *
     * @param Database $db
     * @param string   $path
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $path,
    ) {
    }

    /**
     * Run all pending migrations.
     *
     * @return list<string>
     * @throws Throwable
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $pending = array_values(array_diff($this->getFiles(), $this->getRan()));

        if ($pending === []) {
            return [];
        }

        $batch = $this->getLastBatch() + 1;

        foreach ($pending as $file) {
            $migration = $this->loadMigration($file);
            $this->db->transaction(function () use ($migration, $file, $batch): void {
                $migration->up($this->db->getPdo());
                $this->db->query(
                    'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
                    [$file, $batch],
                );
            });
        }

        return $pending;
    }

    /**
     * Roll back all executed migrations across all batches.
     *
     * @return list<string>
     * @throws Throwable
     */
    public function rollbackAll(): array
    {
        $this->ensureMigrationsTable();

        $rolled = [];

        while ($this->getLastBatch() > 0) {
            $rolled = array_merge($rolled, $this->rollback());
        }

        return $rolled;
    }

    /**
     * Drop every table in the current database without running migration down() methods.
     * Disables foreign key checks on MySQL to avoid constraint errors during the drop.
     *
     * @return list<string>  Names of the tables that were dropped.
     */
    public function dropAllTables(): array
    {
        $pdo = $this->db->getPdo();

        /** @var mixed $driverAttr */
        $driverAttr = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driverAttr) ? $driverAttr : '';

        if ($driver === 'mysql') {
            return $this->dropAllTablesMysql($pdo);
        }

        return $this->dropAllTablesSqlite($pdo);
    }

    /**
     * @param \PDO $pdo
     *
     * @return list<string>
     */
    private function dropAllTablesMysql(\PDO $pdo): array
    {
        $stmt = $pdo->query('SHOW TABLES');

        if ($stmt === false) {
            return [];
        }

        /** @var list<list<string>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        /** @var list<string> $tables */
        $tables = array_values(array_filter(
            array_map(static fn (array $row): string => (string) ($row[0] ?? ''), $rows),
            static fn (string $t): bool => $t !== '',
        ));

        if ($tables === []) {
            return [];
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        return $tables;
    }

    /**
     * @param \PDO $pdo
     *
     * @return list<string>
     */
    private function dropAllTablesSqlite(\PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        if ($stmt === false) {
            return [];
        }

        /** @var list<array{name: string}> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        /** @var list<string> $tables */
        $tables = array_values(array_filter(
            array_map(static fn (array $row): string => (string) $row['name'], $rows),
            static fn (string $t): bool => $t !== '',
        ));

        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '"');
        }

        return $tables;
    }

    /**
     * Return the status of every migration file.
     *
     * @return list<array{migration: string, status: string, batch: int|null}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        /** @var list<array{migration: string, batch: int}> $ran */
        $ran = $this->db->query('SELECT migration, batch FROM migrations ORDER BY migration');

        $ranMap = [];
        foreach ($ran as $row) {
            $ranMap[$row['migration']] = $row['batch'];
        }

        $result = [];
        foreach ($this->getFiles() as $file) {
            if (isset($ranMap[$file])) {
                $result[] = ['migration' => $file, 'status' => 'Ran', 'batch' => $ranMap[$file]];
            } else {
                $result[] = ['migration' => $file, 'status' => 'Pending', 'batch' => null];
            }
        }

        return $result;
    }

    /**
     * Roll back the last batch of migrations.
     *
     * @return list<string>
     * @throws Throwable
     */
    public function rollback(): array
    {
        $this->ensureMigrationsTable();

        $batch = $this->getLastBatch();

        if ($batch === 0) {
            return [];
        }

        /** @var list<string> $ran */
        $ran = array_column(
            $this->db->query(
                'SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC',
                [$batch],
            ),
            'migration',
        );

        $this->db->transaction(function () use ($ran): void {
            foreach ($ran as $file) {
                $migration = $this->loadMigration($file);
                try {
                    $migration->down($this->db->getPdo());
                } catch (Throwable $e) {
                    throw new MigrationException(
                        "Failed to roll back migration '{$file}': " . $e->getMessage(),
                        0,
                        $e,
                    );
                }
                $this->db->query('DELETE FROM migrations WHERE migration = ?', [$file]);
            }
        });

        return $ran;
    }

    /**
     * @return void
     */
    private function ensureMigrationsTable(): void
    {
        $this->db->query('
            CREATE TABLE IF NOT EXISTS migrations (
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            )
        ');
    }

    /**
     * @return list<string>
     */
    private function getRan(): array
    {
        /** @var list<string> $ran */
        $ran = array_column(
            $this->db->query('SELECT migration FROM migrations ORDER BY migration'),
            'migration',
        );

        return $ran;
    }

    /**
     * @return list<string>
     */
    private function getFiles(): array
    {
        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        $files = array_map('basename', $files);
        sort($files);

        return $files;
    }

    /**
     * @param string $file
     *
     * @return MigrationInterface
     */
    private function loadMigration(string $file): MigrationInterface
    {
        if (!isset($this->loaded[$file])) {
            /** @var MigrationInterface $instance */
            $instance = require $this->path . DIRECTORY_SEPARATOR . $file;
            $this->loaded[$file] = $instance;
        }

        return $this->loaded[$file];
    }

    /**
     * @return int
     */
    private function getLastBatch(): int
    {
        $result = $this->db->query('SELECT MAX(batch) as batch FROM migrations');

        /** @var int $batch */
        $batch = $result[0]['batch'] ?? 0;

        return $batch;
    }
}
