<?php

declare(strict_types=1);

namespace Tests\Migration;

use EzPhp\Database\Database;
use EzPhp\Migration\SeederRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class SeederRunnerTest
 *
 * @package Tests\Migration
 */
#[CoversClass(SeederRunner::class)]
#[UsesClass(Database::class)]
final class SeederRunnerTest extends TestCase
{
    private string $path;

    private Database $db;

    private SeederRunner $runner;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ez-php-seeder-' . uniqid();
        mkdir($this->path);
        $this->db = new Database('sqlite::memory:', '', '');
        $this->db->query('CREATE TABLE items (name TEXT)');
        $this->runner = new SeederRunner($this->db, $this->path);
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
    public function test_run_returns_empty_array_when_no_files(): void
    {
        $this->assertSame([], $this->runner->run());
    }

    /**
     * @return void
     */
    public function test_run_returns_empty_array_when_directory_missing(): void
    {
        $runner = new SeederRunner($this->db, '/nonexistent/path');
        $this->assertSame([], $runner->run());
    }

    /**
     * @return void
     */
    public function test_run_executes_all_seeders(): void
    {
        file_put_contents($this->path . '/ASeeder.php', $this->seederStub('Alice'));
        file_put_contents($this->path . '/BSeeder.php', $this->seederStub('Bob'));

        $ran = $this->runner->run();

        $this->assertSame(['ASeeder.php', 'BSeeder.php'], $ran);

        $rows = $this->db->query('SELECT name FROM items ORDER BY name');
        $this->assertCount(2, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
    }

    /**
     * @return void
     */
    public function test_run_executes_seeders_in_alphabetical_order(): void
    {
        file_put_contents($this->path . '/ZSeeder.php', $this->seederStub('Zoe'));
        file_put_contents($this->path . '/ASeeder.php', $this->seederStub('Alice'));

        $ran = $this->runner->run();

        $this->assertSame(['ASeeder.php', 'ZSeeder.php'], $ran);
    }

    /**
     * @return void
     */
    public function test_run_with_specific_file_runs_only_that_file(): void
    {
        file_put_contents($this->path . '/ASeeder.php', $this->seederStub('Alice'));
        file_put_contents($this->path . '/BSeeder.php', $this->seederStub('Bob'));

        $ran = $this->runner->run('ASeeder.php');

        $this->assertSame(['ASeeder.php'], $ran);

        $rows = $this->db->query('SELECT name FROM items');
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    /**
     * @return void
     */
    public function test_run_with_unknown_file_returns_empty(): void
    {
        file_put_contents($this->path . '/ASeeder.php', $this->seederStub('Alice'));

        $ran = $this->runner->run('NoSuchSeeder.php');

        $this->assertSame([], $ran);
    }

    /**
     * @return void
     */
    public function test_get_files_returns_sorted_basenames(): void
    {
        file_put_contents($this->path . '/BSeeder.php', '<?php return null;');
        file_put_contents($this->path . '/ASeeder.php', '<?php return null;');

        $this->assertSame(['ASeeder.php', 'BSeeder.php'], $this->runner->getFiles());
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
