<?php

declare(strict_types=1);

namespace EzPhp\Console\Schedule;

use DateTimeInterface;

/**
 * Class Scheduler
 *
 * Registry of scheduled commands. Service providers call command() to register
 * entries; ScheduleRunCommand calls dueCommands() to determine what to run.
 *
 * Usage (in a ServiceProvider boot()):
 *   $scheduler = $app->make(Scheduler::class);
 *   $scheduler->command('queue:work')->everyMinute();
 *   $scheduler->command('cache:prune')->daily();
 *
 * @package EzPhp\Console\Schedule
 */
final class Scheduler
{
    /**
     * @var list<ScheduledCommand>
     */
    private array $commands = [];

    /**
     * Register a command by name and return its ScheduledCommand for frequency configuration.
     *
     * @param string $name The console command name (as registered in the Console).
     *
     * @return ScheduledCommand
     */
    public function command(string $name): ScheduledCommand
    {
        $scheduled = new ScheduledCommand($name);
        $this->commands[] = $scheduled;

        return $scheduled;
    }

    /**
     * Return all scheduled commands that are due at the given time.
     *
     * @param DateTimeInterface $time The moment to check against (typically now).
     *
     * @return list<ScheduledCommand>
     */
    public function dueCommands(DateTimeInterface $time): array
    {
        return array_values(array_filter(
            $this->commands,
            static fn (ScheduledCommand $c): bool => $c->isDue($time),
        ));
    }

    /**
     * Return all registered scheduled commands regardless of due status.
     *
     * @return list<ScheduledCommand>
     */
    public function all(): array
    {
        return $this->commands;
    }
}
