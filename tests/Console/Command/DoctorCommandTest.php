<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\DoctorCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class DoctorCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(DoctorCommand::class)]
final class DoctorCommandTest extends TestCase
{
    private string $envExamplePath;

    /** @var array<string, string|false> */
    private array $savedGetenv = [];

    /** @var array<string, string|null> */
    private array $savedEnv = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $dir = sys_get_temp_dir() . '/ez-php-doctor-' . uniqid();
        mkdir($dir, 0o755, true);
        $this->envExamplePath = $dir . '/.env.example';
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->envExamplePath)) {
            unlink($this->envExamplePath);
        }

        rmdir(dirname($this->envExamplePath));
        $this->restoreEnv();
        parent::tearDown();
    }

    // ─── Metadata ─────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_name(): void
    {
        $cmd = new DoctorCommand($this->envExamplePath);
        $this->assertSame('doctor', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_get_description(): void
    {
        $cmd = new DoctorCommand($this->envExamplePath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_get_help(): void
    {
        $cmd = new DoctorCommand($this->envExamplePath);
        $this->assertStringContainsString('doctor', $cmd->getHelp());
    }

    // ─── Extension check ──────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_handle_outputs_all_section_headers(): void
    {
        file_put_contents($this->envExamplePath, '');

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('=== Extensions ===', $output);
        $this->assertStringContainsString('=== Environment ===', $output);
        $this->assertStringContainsString('=== Database ===', $output);
    }

    /**
     * @return void
     */
    public function test_handle_outputs_required_extensions(): void
    {
        file_put_contents($this->envExamplePath, '');

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('pdo_mysql', $output);
        $this->assertStringContainsString('mbstring', $output);
        $this->assertStringContainsString('intl', $output);
        $this->assertStringContainsString('zip', $output);
    }

    /**
     * @return void
     */
    public function test_handle_outputs_optional_extensions(): void
    {
        file_put_contents($this->envExamplePath, '');

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('redis', $output);
        $this->assertStringContainsString('(optional)', $output);
    }

    // ─── Environment check ────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_handle_env_skips_when_env_example_missing(): void
    {
        // envExamplePath not created — file does not exist
        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('SKIP', $output);
    }

    /**
     * @return void
     */
    public function test_handle_returns_1_when_env_key_missing(): void
    {
        file_put_contents($this->envExamplePath, "DOCTOR_MISSING_KEY_XYZ_9999=\n");

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_handle_env_reports_missing_key(): void
    {
        file_put_contents($this->envExamplePath, "DOCTOR_MISSING_KEY_XYZ_9999=\n");

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('MISSING', $output);
        $this->assertStringContainsString('DOCTOR_MISSING_KEY_XYZ_9999', $output);
    }

    // ─── Database check ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_handle_db_skips_when_host_not_configured(): void
    {
        file_put_contents($this->envExamplePath, '');

        $this->overrideEnv([
            'DB_HOST' => null,
            'DB_DATABASE' => null,
            'DB_USERNAME' => null,
        ]);

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('SKIP', $output);
    }

    /**
     * @return void
     */
    public function test_handle_returns_1_when_db_connection_fails(): void
    {
        file_put_contents($this->envExamplePath, '');

        $this->overrideEnv([
            'DB_HOST' => 'db',
            'DB_DATABASE' => 'nonexistent_db_xyz',
            'DB_USERNAME' => 'wrong_user_xyz',
            'DB_PASSWORD' => 'wrong_password_xyz',
            'DB_PORT' => null,
        ]);

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_handle_db_reports_fail_on_wrong_credentials(): void
    {
        file_put_contents($this->envExamplePath, '');

        $this->overrideEnv([
            'DB_HOST' => 'db',
            'DB_DATABASE' => 'nonexistent_db_xyz',
            'DB_USERNAME' => 'wrong_user_xyz',
            'DB_PASSWORD' => 'wrong_password_xyz',
            'DB_PORT' => null,
        ]);

        $cmd = new DoctorCommand($this->envExamplePath);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('FAIL', $output);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Override environment variables for the duration of a test.
     * Pass null as value to unset the variable.
     *
     * @param array<string, string|null> $vars
     *
     * @return void
     */
    private function overrideEnv(array $vars): void
    {
        foreach ($vars as $key => $value) {
            $existing = $_ENV[$key] ?? null;
            $this->savedEnv[$key] = is_string($existing) ? $existing : null;
            $this->savedGetenv[$key] = getenv($key);

            if ($value === null) {
                unset($_ENV[$key]);
                putenv($key);
            } else {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    /**
     * Restore environment variables saved by overrideEnv().
     *
     * @return void
     */
    private function restoreEnv(): void
    {
        foreach ($this->savedEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        foreach ($this->savedGetenv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv("{$key}={$value}");
            }
        }

        $this->savedEnv = [];
        $this->savedGetenv = [];
    }
}
