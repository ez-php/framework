<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Database\Database;
use Throwable;

/**
 * Class Migrator
 *
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

        foreach ($ran as $file) {
            $migration = $this->loadMigration($file);
            $this->db->transaction(function () use ($migration, $file): void {
                $migration->down($this->db->getPdo());
                $this->db->query('DELETE FROM migrations WHERE migration = ?', [$file]);
            });
        }

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
