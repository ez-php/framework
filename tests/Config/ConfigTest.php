<?php

declare(strict_types=1);

namespace Tests\Config;

use EzPhp\Config\Config;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ConfigTest
 *
 * @package Tests\Config
 */
#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    /**
     * @return Config
     */
    private function makeConfig(): Config
    {
        return new Config([
            'app' => [
                'name' => 'ez-php',
                'debug' => true,
            ],
            'db' => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ]);
    }

    /**
     * @return void
     */
    public function test_get_returns_top_level_value(): void
    {
        $config = new Config(['name' => 'ez-php']);
        $this->assertSame('ez-php', $config->get('name'));
    }

    /**
     * @return void
     */
    public function test_get_returns_nested_value_via_dot_notation(): void
    {
        $this->assertSame('ez-php', $this->makeConfig()->get('app.name'));
    }

    /**
     * @return void
     */
    public function test_get_returns_deeply_nested_value(): void
    {
        $this->assertSame('localhost', $this->makeConfig()->get('db.host'));
    }

    /**
     * @return void
     */
    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertNull($this->makeConfig()->get('missing'));
    }

    /**
     * @return void
     */
    public function test_get_returns_custom_default_for_missing_key(): void
    {
        $this->assertSame('fallback', $this->makeConfig()->get('missing', 'fallback'));
    }

    /**
     * @return void
     */
    public function test_get_returns_default_for_missing_nested_key(): void
    {
        $this->assertNull($this->makeConfig()->get('app.missing'));
    }

    /**
     * @return void
     */
    public function test_get_returns_array_value(): void
    {
        $result = $this->makeConfig()->get('app');
        $this->assertIsArray($result);
        $this->assertSame('ez-php', $result['name']);
    }

    /**
     * @return void
     */
    public function test_get_returns_boolean_value(): void
    {
        $this->assertTrue($this->makeConfig()->get('app.debug'));
    }

    /**
     * @return void
     */
    public function test_get_returns_integer_value(): void
    {
        $this->assertSame(3306, $this->makeConfig()->get('db.port'));
    }
}
