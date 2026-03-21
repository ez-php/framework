<?php

declare(strict_types=1);

namespace Tests\Config;

use EzPhp\Config\ConfigValidator;
use EzPhp\Exceptions\ConfigException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ConfigValidatorTest
 *
 * @package Tests\Config
 */
#[CoversClass(ConfigValidator::class)]
#[UsesClass(ConfigException::class)]
final class ConfigValidatorTest extends TestCase
{
    /**
     * @return void
     */
    public function test_assert_valid_value_passes_for_valid_value(): void
    {
        ConfigValidator::assertValidValue('cache.driver', 'redis', ['array', 'file', 'redis']);
        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     */
    public function test_assert_valid_value_throws_for_invalid_value(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Invalid value 'invalid' for config key 'cache.driver'");

        ConfigValidator::assertValidValue('cache.driver', 'invalid', ['array', 'file', 'redis']);
    }

    /**
     * @return void
     */
    public function test_assert_valid_value_error_message_contains_allowed_values(): void
    {
        try {
            ConfigValidator::assertValidValue('db.driver', 'foobar', ['mysql', 'sqlite']);
            $this->fail('Expected ConfigException');
        } catch (ConfigException $e) {
            $this->assertStringContainsString('mysql', $e->getMessage());
            $this->assertStringContainsString('sqlite', $e->getMessage());
        }
    }

    /**
     * @return void
     */
    public function test_assert_not_empty_passes_for_non_empty_string(): void
    {
        ConfigValidator::assertNotEmpty('db.host', 'localhost');
        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     */
    public function test_assert_not_empty_throws_for_empty_string(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage("Config key 'db.host' must not be empty");

        ConfigValidator::assertNotEmpty('db.host', '');
    }
}
