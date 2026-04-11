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

    // --- Typed accessor helpers ---

    /**
     * @return void
     */
    public function test_string_returns_string_value(): void
    {
        $this->assertSame('ez-php', $this->makeConfig()->string('app.name'));
    }

    /**
     * @return void
     */
    public function test_string_returns_default_for_missing_key(): void
    {
        $this->assertSame('default', $this->makeConfig()->string('missing', 'default'));
    }

    /**
     * @return void
     */
    public function test_string_casts_integer_to_string(): void
    {
        $this->assertSame('3306', $this->makeConfig()->string('db.port'));
    }

    /**
     * @return void
     */
    public function test_int_returns_integer_value(): void
    {
        $this->assertSame(3306, $this->makeConfig()->int('db.port'));
    }

    /**
     * @return void
     */
    public function test_int_returns_default_for_missing_key(): void
    {
        $this->assertSame(42, $this->makeConfig()->int('missing', 42));
    }

    /**
     * @return void
     */
    public function test_float_returns_float_value(): void
    {
        $config = new Config(['scale' => 1.5]);
        $this->assertSame(1.5, $config->float('scale'));
    }

    /**
     * @return void
     */
    public function test_float_returns_default_for_missing_key(): void
    {
        $this->assertSame(3.3, $this->makeConfig()->float('missing', 3.3));
    }

    /**
     * @return void
     */
    public function test_float_casts_integer_to_float(): void
    {
        $this->assertSame(3306.0, $this->makeConfig()->float('db.port'));
    }

    /**
     * @return void
     */
    public function test_float_casts_string_to_float(): void
    {
        $config = new Config(['val' => '2.5']);
        $this->assertSame(2.5, $config->float('val'));
    }

    /**
     * @return void
     */
    public function test_float_returns_default_for_non_scalar_value(): void
    {
        $config = new Config(['val' => ['nested']]);
        $this->assertSame(0.0, $config->float('val'));
    }

    /**
     * @return void
     */
    public function test_array_returns_array_value(): void
    {
        $result = $this->makeConfig()->array('app');
        $this->assertSame(['name' => 'ez-php', 'debug' => true], $result);
    }

    /**
     * @return void
     */
    public function test_array_returns_default_for_missing_key(): void
    {
        $this->assertSame([], $this->makeConfig()->array('missing'));
        $this->assertSame(['a'], $this->makeConfig()->array('missing', ['a']));
    }

    /**
     * @return void
     */
    public function test_array_returns_default_when_value_is_not_array(): void
    {
        $this->assertSame([], $this->makeConfig()->array('app.name'));
    }

    /**
     * @return void
     */
    public function test_bool_returns_boolean_value(): void
    {
        $this->assertTrue($this->makeConfig()->bool('app.debug'));
    }

    /**
     * @return void
     */
    public function test_bool_returns_default_for_missing_key(): void
    {
        $this->assertFalse($this->makeConfig()->bool('missing'));
        $this->assertTrue($this->makeConfig()->bool('missing', true));
    }

    /**
     * @return void
     */
    public function test_bool_handles_string_true(): void
    {
        $config = new Config(['flag' => 'true']);
        $this->assertTrue($config->bool('flag'));
    }

    /**
     * @return void
     */
    public function test_bool_handles_string_false(): void
    {
        $config = new Config(['flag' => 'false']);
        $this->assertFalse($config->bool('flag'));
    }

    /**
     * @return void
     */
    public function test_bool_handles_string_one(): void
    {
        $config = new Config(['flag' => '1']);
        $this->assertTrue($config->bool('flag'));
    }

    /**
     * @return void
     */
    public function test_bool_handles_string_zero(): void
    {
        $config = new Config(['flag' => '0']);
        $this->assertFalse($config->bool('flag'));
    }
}
