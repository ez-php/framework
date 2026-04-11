<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\DbSeedCommand;
use EzPhp\Database\Database;
use EzPhp\Migration\SeederRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class DbSeedCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(DbSeedCommand::class)]
#[UsesClass(SeederRunner::class)]
#[UsesClass(Database::class)]
final class DbSeedCommandTest extends TestCase
{
    private string $path;

    private SeederRunner $runner;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ez-php-dbseed-' . uniqid();
        mkdir($this->path);
        $db = new Database('sqlite::memory:', '', '');
        $db->query('CREATE TABLE items (name TEXT)');
        $this->runner = new SeederRunner($db, $this->path);
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
        $command = new DbSeedCommand($this->runner);

        $this->assertSame('db:seed', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_runs_all_seeders(): void
    {
        file_put_contents($this->path . '/TestSeeder.php', $this->stub('Alice'));

        ob_start();
        $code = (new DbSeedCommand($this->runner))->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Seeded: TestSeeder.php', $output);
    }

    /**
     * @return void
     */
    public function test_prints_no_seeders_when_directory_empty(): void
    {
        ob_start();
        $code = (new DbSeedCommand($this->runner))->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No seeders found.', $output);
    }

    /**
     * @return void
     */
    public function test_refuses_to_run_in_production_without_force(): void
    {
        file_put_contents($this->path . '/TestSeeder.php', $this->stub('Alice'));

        $command = new DbSeedCommand($this->runner, 'production');

        ob_start();
        $code = $command->handle([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_runs_in_production_with_force_flag(): void
    {
        file_put_contents($this->path . '/TestSeeder.php', $this->stub('Alice'));

        $command = new DbSeedCommand($this->runner, 'production');

        ob_start();
        $code = $command->handle(['--force']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Seeded:', $output);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
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
