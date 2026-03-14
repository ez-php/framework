<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class MakeCommandsTest
 *
 * Covers make:controller, make:middleware, and make:provider commands.
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MakeControllerCommand::class)]
#[CoversClass(MakeMiddlewareCommand::class)]
#[CoversClass(MakeProviderCommand::class)]
final class MakeCommandsTest extends TestCase
{
    private string $srcPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->srcPath = sys_get_temp_dir() . '/ez-php-make-' . uniqid();
        mkdir($this->srcPath, 0o755, true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDir($this->srcPath);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param string $path
     *
     * @return void
     */
    private function removeDir(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
        }
        rmdir($path);
    }

    // ─── make:controller ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_controller_creates_file(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['UserController']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Controllers/UserController.php');
    }

    /**
     * @return void
     */
    public function test_make_controller_stub_contains_class_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $cmd->handle(['PostController']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Controllers/PostController.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('PostController', $content);
        $this->assertStringContainsString('EzPhp\\Http\\Request', $content);
    }

    /**
     * @return void
     */
    public function test_make_controller_returns_1_without_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_controller_returns_1_if_already_exists(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $cmd->handle(['UserController']);
        $code = $cmd->handle(['UserController']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_controller_returns_1_for_invalid_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['123invalid']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_controller_get_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);
        $this->assertSame('make:controller', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_controller_get_description(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_controller_get_help(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);
        $this->assertStringContainsString('make:controller', $cmd->getHelp());
    }

    // ─── make:middleware ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_middleware_creates_file(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['AuthMiddleware']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Middleware/AuthMiddleware.php');
    }

    /**
     * @return void
     */
    public function test_make_middleware_stub_implements_middleware_interface(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $cmd->handle(['CorsMiddleware']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Middleware/CorsMiddleware.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('MiddlewareInterface', $content);
        $this->assertStringContainsString('CorsMiddleware', $content);
    }

    /**
     * @return void
     */
    public function test_make_middleware_returns_1_without_name(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_middleware_returns_1_if_already_exists(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $cmd->handle(['AuthMiddleware']);
        $code = $cmd->handle(['AuthMiddleware']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_middleware_get_name(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);
        $this->assertSame('make:middleware', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_middleware_get_description(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_middleware_get_help(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);
        $this->assertStringContainsString('make:middleware', $cmd->getHelp());
    }

    // ─── make:provider ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_provider_creates_file(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['AppServiceProvider']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Providers/AppServiceProvider.php');
    }

    /**
     * @return void
     */
    public function test_make_provider_stub_extends_service_provider(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $cmd->handle(['MyProvider']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Providers/MyProvider.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('ServiceProvider', $content);
        $this->assertStringContainsString('MyProvider', $content);
    }

    /**
     * @return void
     */
    public function test_make_provider_returns_1_without_name(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_provider_returns_1_if_already_exists(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $cmd->handle(['AppServiceProvider']);
        $code = $cmd->handle(['AppServiceProvider']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_provider_get_name(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);
        $this->assertSame('make:provider', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_provider_get_description(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_provider_get_help(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);
        $this->assertStringContainsString('make:provider', $cmd->getHelp());
    }
}
