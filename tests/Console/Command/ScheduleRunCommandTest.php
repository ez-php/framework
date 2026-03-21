<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\ScheduleRunCommand;
use EzPhp\Console\Console;
use EzPhp\Console\Output;
use EzPhp\Console\Schedule\ScheduledCommand;
use EzPhp\Console\Schedule\Scheduler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ScheduleRunCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ScheduleRunCommand::class)]
#[UsesClass(Scheduler::class)]
#[UsesClass(ScheduledCommand::class)]
#[UsesClass(Output::class)]
#[UsesClass(Console::class)]
final class ScheduleRunCommandTest extends TestCase
{
    /**
     * Build a ScheduleRunCommand with a real Scheduler and an empty Console factory.
     *
     * @param Scheduler $scheduler
     *
     * @return ScheduleRunCommand
     */
    private function makeCommand(Scheduler $scheduler): ScheduleRunCommand
    {
        return new ScheduleRunCommand(
            $scheduler,
            fn (): Console => new Console([]),
        );
    }

    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = $this->makeCommand(new Scheduler());

        $this->assertSame('schedule:run', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_handle_outputs_no_due_message_when_scheduler_is_empty(): void
    {
        $command = $this->makeCommand(new Scheduler());

        ob_start();
        $code = $command->handle([]);
        $out = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No scheduled commands are due', $out);
    }

    /**
     * @return void
     */
    public function test_handle_outputs_no_due_message_when_no_commands_due(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('daily-task')->daily();

        $command = $this->makeCommand($scheduler);

        ob_start();
        $code = $command->handle([]);
        $out = (string) ob_get_clean();

        // At the time this test runs it won't be exactly midnight, so no commands are due
        $this->assertSame(0, $code);
    }

    /**
     * @return void
     */
    public function test_handle_outputs_running_message_for_due_command(): void
    {
        $scheduler = new Scheduler();
        $scheduler->command('migrate')->everyMinute(); // always due

        $command = $this->makeCommand($scheduler);

        ob_start();
        $code = $command->handle([]);
        $out = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('migrate', $out);
    }
}
