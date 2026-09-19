<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\CompletionGenerator;
use EzPhp\Console\Output;

/**
 * Class CompletionGenerateCommand
 *
 * Prints a bash or zsh shell-completion script for the CLI, generated from
 * every currently registered command via CompletionGenerator.
 *
 * Usage:
 *   ez completion:generate bash >> ~/.bashrc
 *   ez completion:generate zsh > ~/.zsh/completions/_ez
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class CompletionGenerateCommand implements CommandInterface
{
    /**
     * CompletionGenerateCommand Constructor
     *
     * @param list<CommandInterface> $commands All commands registered in the Console.
     */
    public function __construct(
        private array $commands,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'completion:generate';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Print a bash or zsh shell-completion script for the CLI';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez completion:generate <bash|zsh>\n\n"
            . "Prints a completion script generated from every registered command.\n"
            . "Redirect the output into your shell's completion setup, e.g.:\n"
            . "  ez completion:generate bash >> ~/.bashrc\n"
            . '  ez completion:generate zsh > ~/.zsh/completions/_ez';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $shell = $args[0] ?? '';
        $generator = new CompletionGenerator($this->commands);

        switch ($shell) {
            case 'bash':
                Output::line($generator->bash());

                return 0;
            case 'zsh':
                Output::line($generator->zsh());

                return 0;
            default:
                Output::error("Unknown shell '{$shell}'. Usage: ez completion:generate <bash|zsh>");

                return 1;
        }
    }
}
