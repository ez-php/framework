<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use Closure;
use EzPhp\Console\Command\MigrateFreshCommand;
use EzPhp\Contracts\Schema\SchemaInterface;
use EzPhp\Database\Database;
use EzPhp\Migration\Migrator;
use EzPhp\Migration\SeederRunner;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Class MigrateFreshCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MigrateFreshCommand::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Database::class)]
#[UsesClass(SeederRunner::class)]
final class MigrateFreshCommandTest extends TestCase
{
    private string $path;

    private string $seederPath;

    private Database $db;

    private Migrator $migrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:', '', '');
        $this->db->query('CREATE TABLE IF NOT EXISTS items (name TEXT)');
        $this->path = sys_get_temp_dir() . '/ez-php-fresh-cmd-' . uniqid();
        $this->seederPath = sys_get_temp_dir() . '/ez-php-fresh-seeders-' . uniqid();
        mkdir($this->path);
        mkdir($this->seederPath);

        $pdo = $this->db->getPdo();
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
        foreach (glob($this->seederPath . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->path);
        rmdir($this->seederPath);
    }

    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new MigrateFreshCommand($this->migrator, new SeederRunner($this->db, $this->seederPath));

        $this->assertSame('migrate:fresh', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_rolls_back_and_re_migrates(): void
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

        $command = new MigrateFreshCommand($this->migrator, new SeederRunner($this->db, $this->seederPath));

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Rolled back:', $output);
        $this->assertStringContainsString('Migrated:', $output);
    }

    /**
     * --seed flag runs seeders after migration.
     *
     * @return void
     * @throws Throwable
     */
    public function test_fresh_with_seed_flag_runs_seeders(): void
    {
        file_put_contents($this->seederPath . '/TestSeeder.php', <<<'PHP'
            <?php
            use EzPhp\Database\Database;
            use EzPhp\Migration\SeederInterface;
            return new class implements SeederInterface {
                public function run(Database $db): void {
                    $db->execute('INSERT INTO items (name) VALUES (?)', ['seeded']);
                }
            };
            PHP);

        $command = new MigrateFreshCommand($this->migrator, new SeederRunner($this->db, $this->seederPath));

        ob_start();
        $code = $command->handle(['--seed']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Seeded: TestSeeder.php', $output);

        $rows = $this->db->query('SELECT name FROM items');
        $this->assertCount(1, $rows);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_fresh_with_no_migrations(): void
    {
        $command = new MigrateFreshCommand($this->migrator, new SeederRunner($this->db, $this->seederPath));

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }
}
