<?php

declare(strict_types=1);

namespace Tests\Migration;

use Closure;
use EzPhp\Contracts\Schema\SchemaInterface;
use EzPhp\Database\Database;
use EzPhp\Migration\MigrationException;
use EzPhp\Migration\Migrator;
use PDO;
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

    private SchemaInterface $schema;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:', '', '');
        $this->path = sys_get_temp_dir() . '/ez-php-migrations-' . uniqid();
        mkdir($this->path);

        $pdo = $this->db->getPdo();
        $this->schema = new class ($pdo) implements SchemaInterface {
            public function __construct(private readonly PDO $pdo)
            {
            }

            public function create(string $table, Closure $callback): void
            {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS \"{$table}\" (id INTEGER PRIMARY KEY)");
            }

            public function table(string $table, Closure $callback): void
            {
            }

            public function drop(string $table): void
            {
                $this->pdo->exec("DROP TABLE \"{$table}\"");
            }

            public function dropIfExists(string $table): void
            {
                $this->pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
            }

            public function hasTable(string $table): bool
            {
                return false;
            }

            public function hasColumn(string $table, string $column): bool
            {
                return false;
            }

            public function rename(string $from, string $to): void
            {
            }
        };

        $schema = $this->schema;
        $this->migrator = new Migrator($this->db, $this->path, static fn () => $schema);
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
     * @param string $table
     *
     * @return void
     */
    private function createMigrationFile(string $filename, string $table): void
    {
        file_put_contents($this->path . '/' . $filename, <<<PHP
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\EzPhp\Contracts\Schema\SchemaInterface \$schema): void { \$schema->create('$table', fn (\$b) => null); }
                public function down(\EzPhp\Contracts\Schema\SchemaInterface \$schema): void { \$schema->drop('$table'); }
            };
            PHP);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migrate_runs_pending_migrations(): void
    {
        $this->createMigrationFile('0001_create_users_table.php', 'users');

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
        $this->createMigrationFile('0001_create_users_table.php', 'users');

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
        $this->createMigrationFile('0001_create_users_table.php', 'users');
        $this->createMigrationFile('0002_create_posts_table.php', 'posts');

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
        $this->createMigrationFile('0001_create_users_table.php', 'users');

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
        $this->createMigrationFile('0001_create_users_table.php', 'users');
        $this->migrator->migrate();

        $this->createMigrationFile('0002_create_posts_table.php', 'posts');
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
        $this->createMigrationFile('0001_create_users_table.php', 'users');

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
        $this->createMigrationFile('0001_create_users_table.php', 'users');
        $this->migrator->migrate();

        $this->createMigrationFile('0002_create_posts_table.php', 'posts');
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
        $this->createMigrationFile('0001_create_users_table.php', 'users');
        $this->createMigrationFile('0002_create_posts_table.php', 'posts');

        $this->migrator->migrate();

        // Add a third that hasn't run
        $this->createMigrationFile('0003_create_tags_table.php', 'tags');

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
     * @param string $table
     *
     * @return void
     */
    private function createMigrationFileWithFailingDown(string $filename, string $table): void
    {
        file_put_contents($this->path . '/' . $filename, <<<PHP
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\EzPhp\Contracts\Schema\SchemaInterface \$schema): void { \$schema->create('$table', fn (\$b) => null); }
                public function down(\EzPhp\Contracts\Schema\SchemaInterface \$schema): void {
                    throw new \RuntimeException('down() failed intentionally');
                }
            };
            PHP);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rollback_throws_migration_exception_when_down_fails(): void
    {
        $this->createMigrationFileWithFailingDown('0001_create_users_table.php', 'users');

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
        $this->createMigrationFileWithFailingDown('0001_create_users_table.php', 'users');

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
     * Rollback order is DESC by filename: 0002 is processed first (succeeds),
     * 0001 is processed second (throws). Without a batch-level transaction,
     * 0002 would be deleted from the migrations table before the exception,
     * leaving the table in an inconsistent state. With the batch transaction
     * the entire rollback is undone and both entries remain tracked.
     *
     * @return void
     * @throws Throwable
     */
    public function test_rollback_is_atomic_when_second_down_fails(): void
    {
        $this->createMigrationFile('0002_create_posts_table.php', 'posts');
        $this->createMigrationFileWithFailingDown('0001_create_users_table.php', 'users');

        $this->migrator->migrate();

        try {
            $this->migrator->rollback();
        } catch (MigrationException) {
            // expected — second down() intentionally throws
        }

        // Both entries must still be tracked; the partial delete of 0002 was rolled back
        $status = $this->migrator->status();
        $this->assertSame('Ran', $status[0]['status']); // 0001
        $this->assertSame('Ran', $status[1]['status']); // 0002
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_drop_all_tables_removes_every_table(): void
    {
        $this->createMigrationFile('0001_create_users_table.php', 'users');
        $this->migrator->migrate();

        $dropped = $this->migrator->dropAllTables();

        $this->assertContains('migrations', $dropped);
        $this->assertContains('users', $dropped);

        // migrate() must recreate the migrations table and re-run all files
        $ran = $this->migrator->migrate();
        $this->assertSame(['0001_create_users_table.php'], $ran);
    }

    /**
     * @return void
     */
    public function test_drop_all_tables_returns_empty_when_no_tables_exist(): void
    {
        $dropped = $this->migrator->dropAllTables();
        $this->assertSame([], $dropped);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_migration_file_can_be_loaded_repeatedly_without_error(): void
    {
        $this->createMigrationFile('0001_create_users_table.php', 'users');

        // Each cycle requires the same file for both migrate and rollback.
        // Without the load registry this could cause anonymous class redeclaration.
        for ($i = 0; $i < 3; $i++) {
            $this->migrator->migrate();
            $this->migrator->rollback();
        }

        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     */
    public function test_migrate_throws_when_no_schema_factory_configured(): void
    {
        $migrator = new Migrator($this->db, $this->path);
        $this->createMigrationFile('0001_create_users_table.php', 'users');

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('SchemaInterface');

        $migrator->migrate();
    }
}
