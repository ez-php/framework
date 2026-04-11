<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\DbSetupCommand;
use EzPhp\Console\InputStreamInterface;
use EzPhp\Console\Prompt;
use EzPhp\Database\Database;
use EzPhp\Migration\MigrationException;
use EzPhp\Migration\Migrator;
use EzPhp\Migration\SeederRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Class DbSetupCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(DbSetupCommand::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(SeederRunner::class)]
#[UsesClass(Database::class)]
#[UsesClass(MigrationException::class)]
final class DbSetupCommandTest extends TestCase
{
    private string $migrationPath;

    private string $seederPath;

    private Database $db;

    private Migrator $migrator;

    private SeederRunner $runner;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->db = new Database('sqlite::memory:', '', '');
        $this->db->query('CREATE TABLE items (name TEXT)');

        $this->migrationPath = sys_get_temp_dir() . '/ez-php-setup-migrations-' . uniqid();
        $this->seederPath = sys_get_temp_dir() . '/ez-php-setup-seeders-' . uniqid();
        mkdir($this->migrationPath);
        mkdir($this->seederPath);

        $this->migrator = new Migrator($this->db, $this->migrationPath);
        $this->runner = new SeederRunner($this->db, $this->seederPath);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        foreach (glob($this->migrationPath . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->seederPath . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->migrationPath);
        rmdir($this->seederPath);
    }

    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new DbSetupCommand($this->migrator, $this->runner);

        $this->assertSame('db:setup', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_runs_migrations_and_seeders(): void
    {
        file_put_contents($this->migrationPath . '/2026_01_01_000000_create_t.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void {
                    $pdo->exec('CREATE TABLE t (id INTEGER)');
                }
                public function down(\PDO $pdo): void {
                    $pdo->exec('DROP TABLE t');
                }
            };
            PHP);

        file_put_contents($this->seederPath . '/TestSeeder.php', $this->seederStub('Alice'));

        $command = new DbSetupCommand($this->migrator, $this->runner);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Migrated:', $output);
        $this->assertStringContainsString('Seeded: TestSeeder.php', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_prints_nothing_to_migrate_when_up_to_date(): void
    {
        $command = new DbSetupCommand($this->migrator, $this->runner);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_prints_no_seeders_when_directory_empty(): void
    {
        $command = new DbSetupCommand($this->migrator, $this->runner);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No seeders found.', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_refresh_flag_rolls_back_and_re_migrates(): void
    {
        file_put_contents($this->migrationPath . '/2026_01_01_000000_create_t.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER)');
                }
                public function down(\PDO $pdo): void {
                    $pdo->exec('DROP TABLE IF EXISTS t');
                }
            };
            PHP);

        $this->migrator->migrate();

        $command = new DbSetupCommand($this->migrator, $this->runner);

        ob_start();
        $code = $command->handle(['--refresh']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Rolled back:', $output);
        $this->assertStringContainsString('Migrated:', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_refresh_hard_drops_all_tables_and_re_migrates(): void
    {
        file_put_contents($this->migrationPath . '/2026_01_01_000000_create_t.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER)');
                }
                public function down(\PDO $pdo): void {
                    $pdo->exec('DROP TABLE IF EXISTS t');
                }
            };
            PHP);

        $this->migrator->migrate();

        $command = new DbSetupCommand($this->migrator, $this->runner);

        ob_start();
        $code = $command->handle(['--refresh-hard']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Dropped:', $output);
        $this->assertStringContainsString('Migrated:', $output);
    }

    /**
     * When --refresh fails and the user confirms, a hard refresh is performed instead.
     *
     * @return void
     * @throws Throwable
     */
    public function test_refresh_fallback_to_hard_on_rollback_failure_confirmed(): void
    {
        file_put_contents($this->migrationPath . '/2026_01_01_000000_create_t.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER)');
                }
                public function down(\PDO $pdo): void {
                    throw new \RuntimeException('down() intentionally broken');
                }
            };
            PHP);

        $this->migrator->migrate();

        $command = new DbSetupCommand($this->migrator, $this->runner, 'local', $this->makePrompt(['y']));

        ob_start();
        $code = $command->handle(['--refresh']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Dropped:', $output);
        $this->assertStringContainsString('Migrated:', $output);
    }

    /**
     * When --refresh fails and the user declines, the command returns 1.
     *
     * @return void
     * @throws Throwable
     */
    public function test_refresh_returns_failure_when_rollback_fails_and_user_declines(): void
    {
        file_put_contents($this->migrationPath . '/2026_01_01_000000_create_t.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void {
                    $pdo->exec('CREATE TABLE IF NOT EXISTS t (id INTEGER)');
                }
                public function down(\PDO $pdo): void {
                    throw new \RuntimeException('down() intentionally broken');
                }
            };
            PHP);

        $this->migrator->migrate();

        $command = new DbSetupCommand($this->migrator, $this->runner, 'local', $this->makePrompt(['n']));

        ob_start();
        $code = $command->handle(['--refresh']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_refuses_to_seed_in_production_without_force(): void
    {
        file_put_contents($this->seederPath . '/TestSeeder.php', $this->seederStub('Alice'));

        $command = new DbSetupCommand($this->migrator, $this->runner, 'production');

        ob_start();
        $code = $command->handle([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_seeds_in_production_with_force_flag(): void
    {
        file_put_contents($this->seederPath . '/TestSeeder.php', $this->seederStub('Alice'));

        $command = new DbSetupCommand($this->migrator, $this->runner, 'production');

        ob_start();
        $code = $command->handle(['--force']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Seeded: TestSeeder.php', $output);
    }

    /**
     * Build a Prompt backed by a fixed list of answer lines.
     *
     * @param list<string> $lines
     *
     * @return Prompt
     */
    private function makePrompt(array $lines): Prompt
    {
        $stream = new class ($lines) implements InputStreamInterface {
            private int $pos = 0;

            /** @param list<string> $lines */
            public function __construct(private array $lines)
            {
            }

            public function readLine(): string
            {
                return $this->lines[$this->pos++] ?? '';
            }
        };

        return new Prompt($stream);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function seederStub(string $name): string
    {
        return <<<PHP
            <?php
            use EzPhp\\Database\\Database;
            use EzPhp\\Migration\\SeederInterface;
            return new class implements SeederInterface {
                public function run(Database \$db): void {
                    \$db->execute('INSERT INTO items (name) VALUES (?)', ['{$name}']);
                }
            };
            PHP;
    }
}
