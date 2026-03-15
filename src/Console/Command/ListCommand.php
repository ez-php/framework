<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;

/**
 * Class ListCommand
 *
 * Lists all registered console commands with their short description.
 *
 * @package EzPhp\Console\Command
 */
final class ListCommand implements CommandInterface
{
    /**
     * ListCommand Constructor
     *
     * @param list<CommandInterface> $commands All commands registered in the Console.
     */
    public function __construct(private readonly array $commands)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'list';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'List all available commands';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez list';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        echo Output::colorize('Available commands:', 33) . "\n";

        foreach ($this->commands as $command) {
            echo sprintf(
                '  %s  %s' . "\n",
                Output::colorize(sprintf('%-24s', $command->getName()), 32),
                $command->getDescription(),
            );
        }

        return 0;
    }
}
