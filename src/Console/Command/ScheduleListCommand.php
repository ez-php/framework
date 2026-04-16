<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use DateTimeImmutable;
use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Console\Schedule\Scheduler;

/**
 * Class ScheduleListCommand
 *
 * Lists all registered scheduled commands with their configured frequency
 * and the next time they will be due.
 *
 * Usage:
 *   ez schedule:list
 *
 * @package EzPhp\Console\Command
 */
final class ScheduleListCommand implements CommandInterface
{
    /**
     * ScheduleListCommand Constructor
     *
     * @param Scheduler $scheduler
     */
    public function __construct(private readonly Scheduler $scheduler)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'schedule:list';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'List all scheduled commands with their frequency and next run time';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez schedule:list\n\nDisplays all commands registered with the Scheduler, their frequency, and the next time each one will run.";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $commands = $this->scheduler->all();

        if ($commands === []) {
            Output::line('No scheduled commands registered.');

            return 0;
        }

        $now = new DateTimeImmutable();

        foreach ($commands as $cmd) {
            $next = $cmd->nextRunAfter($now);
            $nextLabel = $next !== null ? $next->format('Y-m-d H:i') : 'never';

            Output::line(sprintf(
                '  %-30s  %-26s  next: %s',
                $cmd->getCommand(),
                $cmd->getFrequencyDescription(),
                $nextLabel,
            ));
        }

        return 0;
    }
}
