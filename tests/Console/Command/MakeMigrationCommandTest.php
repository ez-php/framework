<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MakeMigrationCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class MakeMigrationCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MakeMigrationCommand::class)]
final class MakeMigrationCommandTest extends TestCase
{
    private string $path;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ez-php-make-migration-' . uniqid();
        mkdir($this->path);
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
        $command = new MakeMigrationCommand($this->path);

        $this->assertSame('make:migration', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    /**
     * @return void
     */
    public function test_creates_migration_file(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $code = $command->handle(['create_users_table']);
        ob_get_clean();

        $this->assertSame(0, $code);

        $files = glob($this->path . '/*.php') ?: [];
        $this->assertCount(1, $files);
    }

    /**
     * @return void
     */
    public function test_filename_contains_name_and_timestamp(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $command->handle(['create_posts_table']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('create_posts_table', $output);
        $this->assertStringContainsString('Created:', $output);

        $files = glob($this->path . '/*create_posts_table.php') ?: [];
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/\d{4}_\d{2}_\d{2}_\d{6}_create_posts_table\.php/', basename($files[0]));
    }

    /**
     * @return void
     */
    public function test_generated_file_contains_valid_stub(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $command->handle(['create_orders_table']);
        ob_get_clean();

        $files = glob($this->path . '/*.php') ?: [];
        $content = file_get_contents($files[0]);

        $this->assertIsString($content);
        $this->assertStringContainsString('MigrationInterface', $content);
        $this->assertStringContainsString('SchemaInterface', $content);
        $this->assertStringContainsString('function up', $content);
        $this->assertStringContainsString('function down', $content);
        $this->assertStringNotContainsString('// TODO', $content);
    }

    /**
     * @return void
     */
    public function test_create_pattern_generates_schema_create_stub(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $command->handle(['create_users_table']);
        ob_get_clean();

        $files = glob($this->path . '/*.php') ?: [];
        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString("\$schema->create('users'", $content);
        $this->assertStringContainsString('$table->id()', $content);
        $this->assertStringContainsString('$table->timestamps()', $content);
        $this->assertStringContainsString("dropIfExists('users')", $content);
    }

    /**
     * @return void
     */
    public function test_add_columns_pattern_generates_schema_table_stub(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $command->handle(['add_email_to_users_table']);
        ob_get_clean();

        $files = glob($this->path . '/*.php') ?: [];
        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString("\$schema->table('users'", $content);
        $this->assertStringContainsString('dropColumn', $content);
    }

    /**
     * @return void
     */
    public function test_drop_pattern_generates_schema_drop_stub(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $command->handle(['drop_posts_table']);
        ob_get_clean();

        $files = glob($this->path . '/*.php') ?: [];
        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString("\$schema->drop('posts')", $content);
    }

    /**
     * @return void
     */
    public function test_unrecognised_pattern_generates_blank_stub(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $command->handle(['some_custom_migration']);
        ob_get_clean();

        $files = glob($this->path . '/*.php') ?: [];
        $content = (string) file_get_contents($files[0]);

        $this->assertStringContainsString('SchemaInterface', $content);
        $this->assertStringContainsString('function up', $content);
        $this->assertStringContainsString('function down', $content);
    }

    /**
     * @return void
     */
    public function test_returns_1_without_name_argument(): void
    {
        $command = new MakeMigrationCommand($this->path);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);

        $files = glob($this->path . '/*.php') ?: [];
        $this->assertCount(0, $files);
    }
}
