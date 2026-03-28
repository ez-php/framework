<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Config\ConfigLoader;
use EzPhp\Console\Command\ConfigCacheCommand;
use EzPhp\Console\Command\ConfigClearCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ConfigCommandsTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ConfigCacheCommand::class)]
#[CoversClass(ConfigClearCommand::class)]
#[UsesClass(ConfigLoader::class)]
final class ConfigCommandsTest extends TestCase
{
    /** @var string */
    private string $configDir = '';

    /** @var string */
    private string $cachePath = '';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configDir = sys_get_temp_dir() . '/ez-php-config-test-' . uniqid();
        $this->cachePath = sys_get_temp_dir() . '/ez-php-cache-test-' . uniqid() . '/config.php';

        mkdir($this->configDir);

        file_put_contents($this->configDir . '/app.php', '<?php return ["name" => "test"];');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }

        $cacheDir = dirname($this->cachePath);
        if (is_dir($cacheDir)) {
            rmdir($cacheDir);
        }

        array_map('unlink', glob($this->configDir . '/*.php') ?: []);
        rmdir($this->configDir);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_cache_command_name_description_help(): void
    {
        $cmd = new ConfigCacheCommand(new ConfigLoader($this->configDir), $this->cachePath);

        $this->assertSame('config:cache', $cmd->getName());
        $this->assertNotEmpty($cmd->getDescription());
        $this->assertNotEmpty($cmd->getHelp());
    }

    /**
     * @return void
     */
    public function test_clear_command_name_description_help(): void
    {
        $cmd = new ConfigClearCommand($this->cachePath);

        $this->assertSame('config:clear', $cmd->getName());
        $this->assertNotEmpty($cmd->getDescription());
        $this->assertNotEmpty($cmd->getHelp());
    }

    /**
     * @return void
     */
    public function test_cache_command_creates_cache_file(): void
    {
        $cmd = new ConfigCacheCommand(new ConfigLoader($this->configDir), $this->cachePath);

        ob_start();
        $code = $cmd->handle([]);
        ob_end_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->cachePath);
    }

    /**
     * @return void
     */
    public function test_cache_file_contains_valid_php_array(): void
    {
        $cmd = new ConfigCacheCommand(new ConfigLoader($this->configDir), $this->cachePath);

        ob_start();
        $cmd->handle([]);
        ob_end_clean();

        /** @var mixed $loaded */
        $loaded = require $this->cachePath;

        $this->assertIsArray($loaded);
        $this->assertArrayHasKey('app', $loaded);

        /** @var array<string, mixed> $loadedArray */
        $loadedArray = $loaded;
        $app = $loadedArray['app'];
        $this->assertIsArray($app);
        /** @var array<string, mixed> $appArray */
        $appArray = $app;
        $this->assertSame('test', $appArray['name']);
    }

    /**
     * @return void
     */
    public function test_clear_command_removes_cache_file(): void
    {
        // First create the cache
        $cacheCmd = new ConfigCacheCommand(new ConfigLoader($this->configDir), $this->cachePath);

        ob_start();
        $cacheCmd->handle([]);
        ob_end_clean();

        $this->assertFileExists($this->cachePath);

        // Now clear it
        $clearCmd = new ConfigClearCommand($this->cachePath);

        ob_start();
        $code = $clearCmd->handle([]);
        ob_end_clean();

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($this->cachePath);
    }

    /**
     * @return void
     */
    public function test_clear_command_returns_0_when_no_cache_exists(): void
    {
        $cmd = new ConfigClearCommand($this->cachePath);

        ob_start();
        $code = $cmd->handle([]);
        ob_end_clean();

        $this->assertSame(0, $code);
    }
}
