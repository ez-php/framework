<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\DownCommand;
use EzPhp\Console\Command\UpCommand;
use EzPhp\Maintenance\MaintenanceMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class MaintenanceCommandsTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(DownCommand::class)]
#[CoversClass(UpCommand::class)]
#[UsesClass(MaintenanceMode::class)]
final class MaintenanceCommandsTest extends TestCase
{
    private string $marker;

    private MaintenanceMode $mode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marker = sys_get_temp_dir() . '/ez-maint-cmd-' . bin2hex(random_bytes(4)) . '/framework/down';
        $this->mode = new MaintenanceMode($this->marker);
    }

    protected function tearDown(): void
    {
        @unlink($this->marker);
        @rmdir(dirname($this->marker));
        @rmdir(dirname($this->marker, 2));
        parent::tearDown();
    }

    /**
     * @param list<string> $args
     *
     * @return array{int, string}
     */
    private function exec(DownCommand|UpCommand $command, array $args = []): array
    {
        ob_start();
        $code = $command->handle($args);

        return [$code, (string) ob_get_clean()];
    }

    public function test_names(): void
    {
        self::assertSame('down', (new DownCommand($this->mode))->getName());
        self::assertSame('up', (new UpCommand($this->mode))->getName());
        self::assertStringContainsString('--secret', (new DownCommand($this->mode))->getHelp());
    }

    public function test_down_with_options_then_up(): void
    {
        [$code, $out] = $this->exec(new DownCommand($this->mode), ['--retry=90', '--secret=abc-123']);

        self::assertSame(0, $code);
        self::assertStringContainsString('/abc-123', $out);
        self::assertTrue($this->mode->isDown());
        self::assertSame(90, $this->mode->retryAfter());
        self::assertSame('abc-123', $this->mode->secret());

        [$code] = $this->exec(new UpCommand($this->mode));

        self::assertSame(0, $code);
        self::assertFalse($this->mode->isDown());
    }

    public function test_up_when_already_up_is_harmless(): void
    {
        [$code, $out] = $this->exec(new UpCommand($this->mode));

        self::assertSame(0, $code);
        self::assertStringContainsString('not in maintenance mode', $out);
    }

    public function test_down_rejects_invalid_options(): void
    {
        self::assertSame(1, $this->exec(new DownCommand($this->mode), ['--retry=soon'])[0]);
        self::assertSame(1, $this->exec(new DownCommand($this->mode), ['--secret=a/b'])[0]);
        self::assertFalse($this->mode->isDown());
    }
}
