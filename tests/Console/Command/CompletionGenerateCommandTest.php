<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\CompletionGenerateCommand;
use EzPhp\Console\CommandInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class CompletionGenerateCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(CompletionGenerateCommand::class)]
final class CompletionGenerateCommandTest extends TestCase
{
    /**
     * @return CommandInterface
     */
    private function makeStub(string $name): CommandInterface
    {
        return new class ($name) implements CommandInterface {
            public function __construct(private readonly string $commandName)
            {
            }

            public function getName(): string
            {
                return $this->commandName;
            }

            public function getDescription(): string
            {
                return "desc for {$this->commandName}";
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
    }

    public function test_name_description_help(): void
    {
        $command = new CompletionGenerateCommand([]);

        $this->assertSame('completion:generate', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    public function test_prints_bash_script_by_default(): void
    {
        $command = new CompletionGenerateCommand([$this->makeStub('migrate')]);

        ob_start();
        $code = $command->handle(['bash']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('complete -F', $output);
        $this->assertStringContainsString('migrate', $output);
    }

    public function test_prints_zsh_script(): void
    {
        $command = new CompletionGenerateCommand([$this->makeStub('migrate')]);

        ob_start();
        $code = $command->handle(['zsh']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('#compdef', $output);
    }

    public function test_returns_error_for_unknown_shell(): void
    {
        $command = new CompletionGenerateCommand([$this->makeStub('migrate')]);

        ob_start();
        $code = $command->handle(['fish']);
        ob_end_clean();

        $this->assertSame(1, $code);
    }

    public function test_returns_error_when_no_shell_given(): void
    {
        $command = new CompletionGenerateCommand([]);

        ob_start();
        $code = $command->handle([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }
}
