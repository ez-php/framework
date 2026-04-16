<?php

declare(strict_types=1);

namespace Tests\Console\Schedule;

use DateTimeImmutable;
use EzPhp\Console\Schedule\ScheduledCommand;
use EzPhp\Console\Schedule\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class SchedulerTest
 *
 * @package Tests\Console\Schedule
 */
#[CoversClass(Scheduler::class)]
#[UsesClass(ScheduledCommand::class)]
final class SchedulerTest extends TestCase
{
    /**
     * @return void
     */
    public function test_command_returns_scheduled_command(): void
    {
        $scheduler = new Scheduler();
        $entry = $scheduler->command('migrate');

        $this->assertInstanceOf(ScheduledCommand::class, $entry);
        $this->assertSame('migrate', $entry->getCommand());
    }

    /**
     * @return void
     */
    public function test_all_returns_registered_commands(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('migrate');
        $scheduler->command('cache:prune');

        $this->assertCount(2, $scheduler->all());
    }

    /**
     * @return void
     */
    public function test_due_commands_returns_only_due_entries(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('always')->everyMinute();
        $scheduler->command('daily')->daily();

        // midnight on a non-midnight time → only everyMinute is due
        $time = new DateTimeImmutable('2024-06-15 10:30:00');
        $due = $scheduler->dueCommands($time);

        $this->assertCount(1, $due);
        $this->assertSame('always', $due[0]->getCommand());
    }

    /**
     * @return void
     */
    public function test_due_commands_returns_multiple_when_both_due(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('always')->everyMinute();
        $scheduler->command('daily')->daily();

        // midnight → both are due
        $midnight = new DateTimeImmutable('2024-06-15 00:00:00');
        $due = $scheduler->dueCommands($midnight);

        $this->assertCount(2, $due);
    }

    /**
     * @return void
     */
    public function test_due_commands_returns_empty_when_none_due(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('daily')->daily();

        $time = new DateTimeImmutable('2024-06-15 10:30:00');
        $due = $scheduler->dueCommands($time);

        $this->assertCount(0, $due);
    }

    // ── ScheduledCommand ──────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_every_minute_is_always_due(): void
    {
        $cmd = new ScheduledCommand('task');
        $cmd->everyMinute();

        $this->assertTrue($cmd->isDue(new DateTimeImmutable('2024-01-01 12:34:00')));
        $this->assertTrue($cmd->isDue(new DateTimeImmutable('2024-01-01 00:00:00')));
    }

    /**
     * @return void
     */
    public function test_hourly_is_due_at_minute_zero(): void
    {
        $cmd = new ScheduledCommand('task');
        $cmd->hourly();

        $this->assertTrue($cmd->isDue(new DateTimeImmutable('2024-01-01 05:00:00')));
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-01-01 05:30:00')));
    }

    /**
     * @return void
     */
    public function test_daily_is_due_at_midnight_only(): void
    {
        $cmd = new ScheduledCommand('task');
        $cmd->daily();

        $this->assertTrue($cmd->isDue(new DateTimeImmutable('2024-01-01 00:00:00')));
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-01-01 00:01:00')));
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-01-01 12:00:00')));
    }

    /**
     * @return void
     */
    public function test_weekly_is_due_on_sunday_midnight(): void
    {
        $cmd = new ScheduledCommand('task');
        $cmd->weekly();

        // 2024-01-07 is a Sunday
        $this->assertTrue($cmd->isDue(new DateTimeImmutable('2024-01-07 00:00:00')));
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-01-08 00:00:00'))); // Monday
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-01-07 01:00:00'))); // Sunday non-midnight
    }

    /**
     * @return void
     */
    public function test_monthly_is_due_on_first_of_month_at_midnight(): void
    {
        $cmd = new ScheduledCommand('task');
        $cmd->monthly();

        $this->assertTrue($cmd->isDue(new DateTimeImmutable('2024-06-01 00:00:00')));
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-06-02 00:00:00'))); // 2nd
        $this->assertFalse($cmd->isDue(new DateTimeImmutable('2024-06-01 01:00:00'))); // 1st non-midnight
    }

    /**
     * @return void
     */
    public function test_default_is_never_due(): void
    {
        $cmd = new ScheduledCommand('task');

        $this->assertFalse($cmd->isDue(new DateTimeImmutable()));
    }

    // ── nextRunAfter ──────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_next_run_after_returns_null_when_never_due(): void
    {
        $cmd = new ScheduledCommand('task'); // no frequency set

        $this->assertNull($cmd->nextRunAfter(new DateTimeImmutable('2025-01-01 12:00:00')));
    }

    /**
     * @return void
     */
    public function test_next_run_after_returns_next_minute_for_every_minute(): void
    {
        $cmd = (new ScheduledCommand('task'))->everyMinute();
        $after = new DateTimeImmutable('2025-06-15 10:30:00');

        $next = $cmd->nextRunAfter($after);

        $this->assertNotNull($next);
        $this->assertSame('2025-06-15 10:31', $next->format('Y-m-d H:i'));
    }

    /**
     * @return void
     */
    public function test_next_run_after_returns_next_hour_for_hourly(): void
    {
        $cmd = (new ScheduledCommand('task'))->hourly();
        $after = new DateTimeImmutable('2025-06-15 10:30:00');

        $next = $cmd->nextRunAfter($after);

        $this->assertNotNull($next);
        $this->assertSame('2025-06-15 11:00', $next->format('Y-m-d H:i'));
    }

    /**
     * @return void
     */
    public function test_next_run_after_returns_midnight_for_daily(): void
    {
        $cmd = (new ScheduledCommand('task'))->daily();
        $after = new DateTimeImmutable('2025-06-15 10:00:00');

        $next = $cmd->nextRunAfter($after);

        $this->assertNotNull($next);
        $this->assertSame('2025-06-16 00:00', $next->format('Y-m-d H:i'));
    }

    /**
     * @return void
     */
    public function test_frequency_description_is_set(): void
    {
        $cmd = new ScheduledCommand('task');

        $cmd->everyMinute();
        $this->assertNotEmpty($cmd->getFrequencyDescription());
        $this->assertStringContainsString('minute', $cmd->getFrequencyDescription());
    }

    /**
     * @return void
     */
    public function test_fluent_returns_same_instance(): void
    {
        $cmd = new ScheduledCommand('task');
        $result = $cmd->everyMinute();

        $this->assertSame($cmd, $result);
    }
}
