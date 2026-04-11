<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MakeSeederCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class MakeSeederCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MakeSeederCommand::class)]
final class MakeSeederCommandTest extends TestCase
{
    private string $path;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/ez-php-make-seeder-' . uniqid();
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
    public function test_name_description_help(): void
    {
        $command = new MakeSeederCommand($this->path);

        $this->assertSame('make:seeder', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_creates_seeder_file_with_php_extension(): void
    {
        $command = new MakeSeederCommand($this->path);

        ob_start();
        $code = $command->handle(['UserSeeder']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created: UserSeeder.php', $output);
        $this->assertFileExists($this->path . '/UserSeeder.php');
    }

    /**
     * @return void
     */
    public function test_creates_seeder_file_when_name_already_has_extension(): void
    {
        $command = new MakeSeederCommand($this->path);

        ob_start();
        $code = $command->handle(['ItemSeeder.php']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->path . '/ItemSeeder.php');
    }

    /**
     * @return void
     */
    public function test_stub_contains_seeder_interface(): void
    {
        $command = new MakeSeederCommand($this->path);
        ob_start();
        $command->handle(['OrderSeeder']);
        ob_end_clean();

        $contents = (string) file_get_contents($this->path . '/OrderSeeder.php');
        $this->assertStringContainsString('SeederInterface', $contents);
        $this->assertStringContainsString('run(Database', $contents);
    }

    /**
     * @return void
     */
    public function test_fails_when_no_name_given(): void
    {
        $command = new MakeSeederCommand($this->path);

        ob_start();
        $code = $command->handle([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_fails_when_file_already_exists(): void
    {
        file_put_contents($this->path . '/ExistingSeeder.php', '<?php');

        $command = new MakeSeederCommand($this->path);

        ob_start();
        $code = $command->handle(['ExistingSeeder']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }
}
