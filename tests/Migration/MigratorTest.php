<?php

declare(strict_types=1);

namespace Tests\Migration;

use EzPhp\Database\Database;
use EzPhp\Migration\MigrationException;
use EzPhp\Migration\Migrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Class MigratorTest
 *
 * @package Tests\Migration
 */
#[CoversClass(Migrator::class)]
#[CoversClass(MigrationException::class)]
#[UsesClass(Database::class)]
final class MigratorTest extends TestCase
{
    private Database $db;

    private string $path;

    private Migrator $migrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:', '', '');
        $this->path = sys_get_temp_dir() . '/ez-php-migrations-' . uniqid();
        mkdir($this->path);
        $this->migrator = new Migrator($this->db, $this->path);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->path);
    }

    /**
     * @param string $filename
     * @param string $upSql
     * @param string $downSql
     *
     * @return void
     */
    private function createMigrationFile(string $filename, string $upSql, string $downSql): void
    {
        file_put_contents($this->path . '/' . $filename, <<<PHP
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO \$pdo): void { \$pdo->exec('$upSql'); }
                public function down(\PDO \$pdo): void { \$pdo->exec('$downSql'); }
            };
            PHP);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migrate_runs_pending_migrations(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );

        $ran = $this->migrator->migrate();

        $this->assertSame(['0001_create_users_table.php'], $ran);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migrate_returns_empty_when_nothing_pending(): void
    {
        $ran = $this->migrator->migrate();
        $this->assertSame([], $ran);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migrate_skips_already_ran_migrations(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );

        $this->migrator->migrate();
        $ran = $this->migrator->migrate();

        $this->assertSame([], $ran);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migrate_runs_multiple_in_order(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );
        $this->createMigrationFile(
            '0002_create_posts_table.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)',
            'DROP TABLE posts',
        );

        $ran = $this->migrator->migrate();

        $this->assertSame([
            '0001_create_users_table.php',
            '0002_create_posts_table.php',
        ], $ran);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_reverts_last_batch(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );

        $this->migrator->migrate();
        $rolled = $this->migrator->rollback();

        $this->assertSame(['0001_create_users_table.php'], $rolled);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_returns_empty_when_nothing_to_rollback(): void
    {
        $rolled = $this->migrator->rollback();
        $this->assertSame([], $rolled);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_only_reverts_last_batch(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );
        $this->migrator->migrate();

        $this->createMigrationFile(
            '0002_create_posts_table.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)',
            'DROP TABLE posts',
        );
        $this->migrator->migrate();

        $rolled = $this->migrator->rollback();

        $this->assertSame(['0002_create_posts_table.php'], $rolled);

        // batch 1 still tracked — only 0002 is pending again
        $remaining = $this->migrator->migrate();
        $this->assertSame(['0002_create_posts_table.php'], $remaining);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migrate_after_rollback_reruns_migration(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );

        $this->migrator->migrate();
        $this->migrator->rollback();
        $ran = $this->migrator->migrate();

        $this->assertSame(['0001_create_users_table.php'], $ran);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_all_reverts_all_batches(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );
        $this->migrator->migrate();

        $this->createMigrationFile(
            '0002_create_posts_table.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)',
            'DROP TABLE posts',
        );
        $this->migrator->migrate();

        $rolled = $this->migrator->rollbackAll();

        $this->assertContains('0001_create_users_table.php', $rolled);
        $this->assertContains('0002_create_posts_table.php', $rolled);

        // All rolled back — migrate reruns everything
        $ran = $this->migrator->migrate();
        $this->assertSame(['0001_create_users_table.php', '0002_create_posts_table.php'], $ran);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_all_returns_empty_when_nothing_to_rollback(): void
    {
        $rolled = $this->migrator->rollbackAll();
        $this->assertSame([], $rolled);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_status_returns_pending_and_ran(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );
        $this->createMigrationFile(
            '0002_create_posts_table.php',
            'CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT)',
            'DROP TABLE posts',
        );

        $this->migrator->migrate();

        // Add a third that hasn't run
        $this->createMigrationFile(
            '0003_create_tags_table.php',
            'CREATE TABLE tags (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE tags',
        );

        $status = $this->migrator->status();

        $this->assertCount(3, $status);

        $this->assertSame('0001_create_users_table.php', $status[0]['migration']);
        $this->assertSame('Ran', $status[0]['status']);
        $this->assertSame(1, $status[0]['batch']);

        $this->assertSame('0003_create_tags_table.php', $status[2]['migration']);
        $this->assertSame('Pending', $status[2]['status']);
        $this->assertNull($status[2]['batch']);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_status_returns_empty_when_no_files(): void
    {
        $status = $this->migrator->status();
        $this->assertSame([], $status);
    }

    /**
     * @param string $filename
     * @param string $upSql
     *
     * @return void
     */
    private function createMigrationFileWithFailingDown(string $filename, string $upSql): void
    {
        file_put_contents($this->path . '/' . $filename, <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void { $pdo->exec('CREATE_SQL'); }
                public function down(\PDO $pdo): void {
                    throw new \RuntimeException('down() failed intentionally');
                }
            };
            PHP);

        // Replace placeholder with actual SQL
        $content = file_get_contents($this->path . '/' . $filename);
        assert(is_string($content));
        file_put_contents(
            $this->path . '/' . $filename,
            str_replace('CREATE_SQL', $upSql, $content),
        );
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_throws_migration_exception_when_down_fails(): void
    {
        $this->createMigrationFileWithFailingDown(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
        );

        $this->migrator->migrate();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('0001_create_users_table.php');

        $this->migrator->rollback();
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_leaves_migration_tracked_when_down_fails(): void
    {
        $this->createMigrationFileWithFailingDown(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
        );

        $this->migrator->migrate();

        try {
            $this->migrator->rollback();
        } catch (MigrationException) {
            // expected
        }

        $status = $this->migrator->status();
        $this->assertSame('Ran', $status[0]['status']);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migration_file_can_be_loaded_repeatedly_without_error(): void
    {
        $this->createMigrationFile(
            '0001_create_users_table.php',
            'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)',
            'DROP TABLE users',
        );

        // Each cycle requires the same file for both migrate and rollback.
        // Without the load registry this could cause anonymous class redeclaration.
        for ($i = 0; $i < 3; $i++) {
            $this->migrator->migrate();
            $this->migrator->rollback();
        }

        $this->expectNotToPerformAssertions();
    }
}
