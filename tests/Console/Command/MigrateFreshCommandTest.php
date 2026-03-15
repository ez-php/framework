<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MigrateFreshCommand;
use EzPhp\Database\Database;
use EzPhp\Migration\Migrator;
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
final class MigrateFreshCommandTest extends TestCase
{
    private string $path;

    private Migrator $migrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $db = new Database('sqlite::memory:', '', '');
        $this->path = sys_get_temp_dir() . '/ez-php-fresh-cmd-' . uniqid();
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
        $command = new MigrateFreshCommand($this->migrator);

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
                public function up(\PDO $pdo): void {
                    $pdo->exec('CREATE TABLE t (id INTEGER)');
                }
                public function down(\PDO $pdo): void {
                    $pdo->exec('DROP TABLE t');
                }
            };
            PHP);

        $this->migrator->migrate();

        $command = new MigrateFreshCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Rolled back:', $output);
        $this->assertStringContainsString('Migrated:', $output);
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function test_fresh_with_no_migrations(): void
    {
        $command = new MigrateFreshCommand($this->migrator);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Nothing to migrate.', $output);
    }
}
