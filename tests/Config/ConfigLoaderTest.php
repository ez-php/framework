<?php

declare(strict_types=1);

namespace Tests\Config;

use EzPhp\Config\ConfigLoader;
use EzPhp\Exceptions\ConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ConfigLoaderTest
 *
 * @package Tests\Config
 */
#[CoversClass(ConfigLoader::class)]
final class ConfigLoaderTest extends TestCase
{
    /**
     * @return void
     * @throws ConfigException
     */
    public function test_load_returns_array_with_config_keys(): void
    {
        $loader = new ConfigLoader();
        $config = $loader->load();

        $this->assertArrayHasKey('app', $config);
        $this->assertArrayHasKey('db', $config);
    }

    /**
     * @return void
     * @throws ConfigException
     */
    public function test_load_returns_correct_app_config(): void
    {
        $loader = new ConfigLoader();
        $config = $loader->load();

        $appConfig = $config['app'];
        $this->assertIsArray($appConfig);
        $this->assertArrayHasKey('name', $appConfig);
    }

    /**
     * @return void
     * @throws ConfigException
     */
    public function test_load_returns_correct_db_config(): void
    {
        $loader = new ConfigLoader();
        $config = $loader->load();

        $dbConfig = $config['db'];
        $this->assertIsArray($dbConfig);
        $this->assertArrayHasKey('host', $dbConfig);
        $this->assertArrayHasKey('port', $dbConfig);
        $this->assertArrayHasKey('database', $dbConfig);
    }

    /**
     * @return void
     */
    public function test_load_throws_when_config_directory_missing(): void
    {
        $loader = new ConfigLoader('/non/existent/path');
        $this->expectException(ConfigException::class);
        $loader->load();
    }
}
