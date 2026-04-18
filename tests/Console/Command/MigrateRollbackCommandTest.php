<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use Closure;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Contracts\Schema\SchemaInterface;
use EzPhp\Database\Database;
use EzPhp\Migration\Migrator;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Class MigrateRollbackCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MigrateRollbackCommand::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Database::class)]
final class MigrateRollbackCommandTest extends TestCase
{
    private string $path;

    private Migrator $migrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $db = new Database('sqlite::memory:', '', '');
        $this->path = sys_get_temp_dir() . '/ez-php-rollback-cmd-' . uniqid();
        mkdir($this->path);

        $pdo = $db->getPdo();
        $schema = new class ($pdo) implements SchemaInterface {
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

        $this->migrator = new Migrator($db, $this->path, static fn () => $schema);
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
     * @return void
     */
    public function test_name_and_description(): void
    {
        $command = new MigrateRollbackCommand($this->migrator);

        $this->assertSame('migrate:rollback', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_prints_nothing_to_rollback_when_no_migrations_ran(): void
    {
        $command = new MigrateRollbackCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertSame("Nothing to roll back.\n", $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_prints_rolled_back_files(): void
    {
        file_put_contents($this->path . '/2026_01_01_000000_create_test.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\EzPhp\Contracts\Schema\SchemaInterface $schema): void {
                    $schema->create('t', fn ($b) => null);
                }
                public function down(\EzPhp\Contracts\Schema\SchemaInterface $schema): void {
                    $schema->drop('t');
                }
            };
            PHP);

        $this->migrator->migrate();

        $command = new MigrateRollbackCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Rolled back:', $output);
        $this->assertStringContainsString('2026_01_01_000000_create_test.php', $output);
    }
}
