<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\ServeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ServeCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(ServeCommand::class)]
final class ServeCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new ServeCommand('/var/www/public');

        $this->assertSame('serve', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('ez serve', $command->getHelp());
    }
}
