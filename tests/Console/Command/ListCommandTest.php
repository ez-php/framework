<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\ListCommand;
use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ListCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ListCommand::class)]
#[UsesClass(Output::class)]
final class ListCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new ListCommand([]);

        $this->assertSame('list', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_prints_all_command_names(): void
    {
        $stub = new class () implements CommandInterface {
            public function getName(): string
            {
                return 'foo:bar';
            }

            public function getDescription(): string
            {
                return 'Does foo bar';
            }

            public function getHelp(): string
            {
                return '';
            }

            public function handle(array $args): int
            {
                return 0;
            }
        };

        $command = new ListCommand([$stub]);

        ob_start();
        $code = $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('foo:bar', $output);
        $this->assertStringContainsString('Does foo bar', $output);
    }

    /**
     * @return void
     */
    public function test_prints_header_with_no_commands(): void
    {
        $command = new ListCommand([]);

        ob_start();
        $command->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Available commands', $output);
    }
}
