<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\EnvCheckCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class EnvCheckCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(EnvCheckCommand::class)]
final class EnvCheckCommandTest extends TestCase
{
    private string $tmpFile = '';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpFile = tempnam(sys_get_temp_dir(), 'env_example_');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->tmpFile !== '' && is_file($this->tmpFile)) {
            unlink($this->tmpFile);
        }
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_get_name_returns_env_check(): void
    {
        $cmd = new EnvCheckCommand('/nonexistent/.env.example');
        $this->assertSame('env:check', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_get_description_is_non_empty(): void
    {
        $cmd = new EnvCheckCommand('/nonexistent/.env.example');
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_get_help_is_non_empty(): void
    {
        $cmd = new EnvCheckCommand('/nonexistent/.env.example');
        $this->assertNotEmpty($cmd->getHelp());
    }

    /**
     * @return void
     */
    public function test_returns_zero_when_all_keys_present(): void
    {
        file_put_contents($this->tmpFile, "APP_NAME=\nAPP_DEBUG=false\n");

        $_ENV['APP_NAME'] = 'test';
        $_ENV['APP_DEBUG'] = 'false';

        $cmd = new EnvCheckCommand($this->tmpFile);

        ob_start();
        $exitCode = $cmd->handle([]);
        ob_end_clean();

        $this->assertSame(0, $exitCode);

        unset($_ENV['APP_NAME'], $_ENV['APP_DEBUG']);
    }

    /**
     * @return void
     */
    public function test_returns_one_when_key_is_missing(): void
    {
        file_put_contents($this->tmpFile, "MISSING_KEY=\n");

        unset($_ENV['MISSING_KEY']);
        putenv('MISSING_KEY'); // unset via putenv

        $cmd = new EnvCheckCommand($this->tmpFile);

        ob_start();
        $exitCode = $cmd->handle([]);
        ob_end_clean();

        $this->assertSame(1, $exitCode);
    }

    /**
     * @return void
     */
    public function test_outputs_ok_for_present_keys(): void
    {
        file_put_contents($this->tmpFile, "PRESENT_KEY=value\n");
        $_ENV['PRESENT_KEY'] = 'something';

        $cmd = new EnvCheckCommand($this->tmpFile);

        ob_start();
        $cmd->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('OK', (string) $output);
        $this->assertStringContainsString('PRESENT_KEY', (string) $output);

        unset($_ENV['PRESENT_KEY']);
    }

    /**
     * @return void
     */
    public function test_outputs_missing_for_absent_keys(): void
    {
        file_put_contents($this->tmpFile, "ABSENT_KEY=\n");

        unset($_ENV['ABSENT_KEY']);
        putenv('ABSENT_KEY');

        $cmd = new EnvCheckCommand($this->tmpFile);

        ob_start();
        $cmd->handle([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('MISSING', (string) $output);
        $this->assertStringContainsString('ABSENT_KEY', (string) $output);
    }

    /**
     * @return void
     */
    public function test_ignores_comment_lines(): void
    {
        file_put_contents($this->tmpFile, "# This is a comment\nREAL_KEY=value\n");
        $_ENV['REAL_KEY'] = 'set';

        $cmd = new EnvCheckCommand($this->tmpFile);

        ob_start();
        $exitCode = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        unset($_ENV['REAL_KEY']);
    }

    /**
     * @return void
     */
    public function test_ignores_lines_without_equals(): void
    {
        file_put_contents($this->tmpFile, "NOT_A_KEY\nREAL_KEY=\n");
        $_ENV['REAL_KEY'] = 'set';

        $cmd = new EnvCheckCommand($this->tmpFile);

        ob_start();
        $exitCode = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        unset($_ENV['REAL_KEY']);
    }

    /**
     * @return void
     */
    public function test_returns_zero_when_example_file_does_not_exist(): void
    {
        $cmd = new EnvCheckCommand('/totally/nonexistent/.env.example');

        ob_start();
        $exitCode = $cmd->handle([]);
        ob_end_clean();

        $this->assertSame(0, $exitCode);
    }
}
