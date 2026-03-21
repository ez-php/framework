<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use DateTimeImmutable;
use EzPhp\Console\CommandInterface;
use EzPhp\Console\Console;
use EzPhp\Console\Output;
use EzPhp\Console\Schedule\Scheduler;

/**
 * Class ScheduleRunCommand
 *
 * Runs all console commands that are currently due according to the Scheduler.
 * Intended to be triggered by a system cron entry every minute:
 *
 *   * * * * * docker compose exec app ez schedule:run
 *
 * The console factory closure is used instead of injecting Console directly to
 * avoid a circular dependency: Console contains ScheduleRunCommand, and
 * ScheduleRunCommand needs Console. The closure resolves Console lazily at
 * handle() time, after the singleton is already cached in the container.
 *
 * @package EzPhp\Console\Command
 */
final class ScheduleRunCommand implements CommandInterface
{
    private readonly Scheduler $scheduler;

    /**
     * @var \Closure(): Console
     */
    private readonly \Closure $consoleFactory;

    /**
     * ScheduleRunCommand Constructor
     *
     * @param Scheduler           $scheduler      The scheduler registry.
     * @param \Closure(): Console $consoleFactory Lazy resolver for the Console singleton.
     */
    public function __construct(Scheduler $scheduler, \Closure $consoleFactory)
    {
        $this->scheduler = $scheduler;
        $this->consoleFactory = $consoleFactory;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'schedule:run';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Run all scheduled commands that are currently due';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez schedule:run\n\nRuns all commands registered with the Scheduler that are due at the current minute.\nAdd to system cron to run every minute:\n\n  * * * * * docker compose exec app ez schedule:run";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $due = $this->scheduler->dueCommands(new DateTimeImmutable());

        if ($due === []) {
            Output::line('No scheduled commands are due.');

            return 0;
        }

        $console = ($this->consoleFactory)();

        foreach ($due as $scheduled) {
            Output::info('Running: ' . $scheduled->getCommand());
            $console->run(['schedule:run', $scheduled->getCommand()]);
        }

        return 0;
    }
}
