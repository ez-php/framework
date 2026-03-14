<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Database\Database;
use EzPhp\Migration\Migrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Class MigrateCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MigrateCommand::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Database::class)]
final class MigrateCommandTest extends TestCase
{
    private string $path;

    private Migrator $migrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $db = new Database('sqlite::memory:', '', '');
        $this->path = sys_get_temp_dir() . '/ez-php-migrate-cmd-' . uniqid();
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
    public function test_name_and_description(): void
    {
        $command = new MigrateCommand($this->migrator);

        $this->assertSame('migrate', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_prints_migrated_files(): void
    {
        file_put_contents($this->path . '/2026_01_01_000000_create_test.php', <<<'PHP'
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

        $command = new MigrateCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Migrated:', $output);
        $this->assertStringContainsString('2026_01_01_000000_create_test.php', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_prints_nothing_to_migrate_when_up_to_date(): void
    {
        $command = new MigrateCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertSame("Nothing to migrate.\n", $output);
    }
}
