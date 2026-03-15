<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MigrateStatusCommand;
use EzPhp\Console\Output;
use EzPhp\Database\Database;
use EzPhp\Migration\Migrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Class MigrateStatusCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MigrateStatusCommand::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Database::class)]
#[UsesClass(Output::class)]
final class MigrateStatusCommandTest extends TestCase
{
    private string $path;

    private Migrator $migrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $db = new Database('sqlite::memory:', '', '');
        $this->path = sys_get_temp_dir() . '/ez-php-status-cmd-' . uniqid();
        mkdir($this->path);
        $this->migrator = new Migrator($db, $this->path);
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
    public function test_name_description_help(): void
    {
        $command = new MigrateStatusCommand($this->migrator);

        $this->assertSame('migrate:status', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_no_migration_files(): void
    {
        $command = new MigrateStatusCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No migration files found', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_shows_pending_and_ran_migrations(): void
    {
        file_put_contents($this->path . '/2026_01_01_000000_one.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void { $pdo->exec('CREATE TABLE a (id INTEGER)'); }
                public function down(\PDO $pdo): void { $pdo->exec('DROP TABLE a'); }
            };
            PHP);

        file_put_contents($this->path . '/2026_01_02_000000_two.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void { $pdo->exec('CREATE TABLE b (id INTEGER)'); }
                public function down(\PDO $pdo): void { $pdo->exec('DROP TABLE b'); }
            };
            PHP);

        $this->migrator->migrate();

        // Add a third migration that hasn't run yet
        file_put_contents($this->path . '/2026_01_03_000000_three.php', <<<'PHP'
            <?php
            return new class implements \EzPhp\Migration\MigrationInterface {
                public function up(\PDO $pdo): void { $pdo->exec('CREATE TABLE c (id INTEGER)'); }
                public function down(\PDO $pdo): void { $pdo->exec('DROP TABLE c'); }
            };
            PHP);

        $command = new MigrateStatusCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Ran', $output);
        $this->assertStringContainsString('Pending', $output);
        $this->assertStringContainsString('2026_01_01_000000_one.php', $output);
        $this->assertStringContainsString('2026_01_03_000000_three.php', $output);
    }
}
