<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\ScheduleListCommand;
use EzPhp\Console\Schedule\ScheduledCommand;
use EzPhp\Console\Schedule\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ScheduleListCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ScheduleListCommand::class)]
#[UsesClass(Scheduler::class)]
#[UsesClass(ScheduledCommand::class)]
final class ScheduleListCommandTest extends TestCase
{
    // ── metadata ──────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_command_name_is_schedule_list(): void
    {
        $cmd = new ScheduleListCommand(new Scheduler());

        $this->assertSame('schedule:list', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_description_is_non_empty(): void
    {
        $cmd = new ScheduleListCommand(new Scheduler());

        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_help_is_non_empty(): void
    {
        $cmd = new ScheduleListCommand(new Scheduler());

        $this->assertNotEmpty($cmd->getHelp());
    }

    // ── output ────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_returns_zero_when_no_commands_registered(): void
    {
        $cmd = new ScheduleListCommand(new Scheduler());

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
    }

    /**
     * @return void
     */
    public function test_returns_zero_when_commands_registered(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('queue:work')->everyMinute();

        $cmd = new ScheduleListCommand($scheduler);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
    }

    /**
     * @return void
     */
    public function test_lists_all_registered_commands(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('queue:work')->everyMinute();
        $scheduler->command('cache:prune')->daily();

        $cmd = new ScheduleListCommand($scheduler);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('queue:work', $output);
        $this->assertStringContainsString('cache:prune', $output);
    }

    /**
     * @return void
     */
    public function test_output_includes_frequency_description(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('tick')->everyMinute();

        $cmd = new ScheduleListCommand($scheduler);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('every minute', $output);
    }

    /**
     * @return void
     */
    public function test_output_includes_next_run_time_for_every_minute(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('tick')->everyMinute();

        $cmd = new ScheduleListCommand($scheduler);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('next:', $output);
        // Date format YYYY-MM-DD HH:MM should appear
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $output);
    }

    /**
     * @return void
     */
    public function test_output_shows_never_for_unscheduled_command(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('orphan'); // no frequency set

        $cmd = new ScheduleListCommand($scheduler);

        ob_start();
        $cmd->handle([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('never', $output);
    }
}
