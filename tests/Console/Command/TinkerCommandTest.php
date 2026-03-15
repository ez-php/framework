<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Application\Application;
use EzPhp\Console\Command\TinkerCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class TinkerCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(TinkerCommand::class)]
#[UsesClass(Application::class)]
final class TinkerCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new TinkerCommand(new Application());

        $this->assertSame('tinker', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('psysh', $command->getHelp());
    }

    /**
     * @return void
     */
    public function test_returns_error_when_psysh_not_available(): void
    {
        if (class_exists(\Psy\Shell::class)) {
            $this->markTestSkipped('PsySH is installed — skipping fallback test.');
        }

        $command = new TinkerCommand(new Application());

        $this->expectOutputString('');

        $stderr = fopen('php://memory', 'r+');
        $this->assertIsResource($stderr);

        ob_start();
        $code = $command->handle([]);
        ob_end_clean();

        $this->assertSame(1, $code);
    }
}
