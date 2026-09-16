<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Config\Config;
use EzPhp\Console\Command\ConfigShowCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ConfigShowCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ConfigShowCommand::class)]
#[UsesClass(Config::class)]
final class ConfigShowCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_name_and_description(): void
    {
        $command = new ConfigShowCommand(new Config([]));

        $this->assertSame('config:show', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_prints_scalar_value_for_dot_key(): void
    {
        $config = new Config(['app' => ['debug' => true, 'name' => 'ez-php']]);
        $command = new ConfigShowCommand($config);

        ob_start();
        $code = $command->handle(['app.name']);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('ez-php', (string) $output);
    }

    /**
     * @return void
     */
    public function test_prints_json_for_array_value(): void
    {
        $config = new Config(['db' => ['host' => 'localhost', 'port' => 3306]]);
        $command = new ConfigShowCommand($config);

        ob_start();
        $code = $command->handle(['db']);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('"host": "localhost"', (string) $output);
        $this->assertStringContainsString('"port": 3306', (string) $output);
    }

    /**
     * @return void
     */
    public function test_returns_error_when_no_key_given(): void
    {
        $command = new ConfigShowCommand(new Config([]));

        ob_start();
        $code = $command->handle([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_returns_error_when_key_not_found(): void
    {
        $command = new ConfigShowCommand(new Config(['app' => ['name' => 'ez-php']]));

        ob_start();
        $code = $command->handle(['app.missing']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }
}
