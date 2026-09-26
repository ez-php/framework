<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\ServeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ServeCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ServeCommand::class)]
final class ServeCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new ServeCommand('/var/www/public');

        $this->assertSame('serve', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('ez serve', $command->getHelp());
    }

    /**
     * @return void
     */
    public function test_server_command_uses_the_running_php_binary(): void
    {
        $command = new ServeCommand('/var/www/public');

        $this->assertSame(
            escapeshellarg(PHP_BINARY) . " -S 'localhost:8000' -t '/var/www/public'",
            $command->serverCommand('localhost:8000'),
        );
    }

    /**
     * @return void
     */
    public function test_server_command_escapes_the_address_and_path(): void
    {
        $command = new ServeCommand("/srv/my app's/public");

        $this->assertStringEndsWith(
            ' -S ' . escapeshellarg('0.0.0.0:80; rm -rf /') . ' -t ' . escapeshellarg("/srv/my app's/public"),
            $command->serverCommand('0.0.0.0:80; rm -rf /'),
        );
    }
}
