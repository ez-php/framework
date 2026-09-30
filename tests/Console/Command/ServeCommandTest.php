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
    private string $base = '';

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . '/ez-serve-' . bin2hex(random_bytes(4));
        mkdir($this->base . '/public', 0o755, true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->base));

        parent::tearDown();
    }

    /**
     * Write a stand-in for the PHP interpreter: it appends its arguments to
     * `calls.log`, sleeps `$firstSleep` seconds on its first call only, and
     * exits with `$exitCode`.
     *
     * @param int $exitCode
     * @param int $firstSleep
     *
     * @return string Path to the executable.
     */
    private function fakeBinary(int $exitCode = 0, int $firstSleep = 0): string
    {
        $log = escapeshellarg($this->base . '/calls.log');
        $path = $this->base . '/fake-php';

        file_put_contents($path, <<<SH
            #!/bin/sh
            echo "\$*" >> {$log}
            if [ "\$(wc -l < {$log})" -eq 1 ]; then sleep {$firstSleep}; fi
            exit {$exitCode}
            SH);
        chmod($path, 0o755);

        return $path;
    }

    /**
     * @return list<string>
     */
    private function calls(): array
    {
        $log = $this->base . '/calls.log';

        return is_file($log) ? (file($log, FILE_IGNORE_NEW_LINES) ?: []) : [];
    }

    /**
     * @param ServeCommand $command
     * @param list<string> $args
     *
     * @return array{int, string}
     */
    private function serve(ServeCommand $command, array $args): array
    {
        ob_start();
        $code = $command->handle($args);

        return [$code, (string) ob_get_clean()];
    }

    /**
     * @return void
     */
    public function test_name_description_help(): void
    {
        $command = new ServeCommand('/var/www/public', errorStream: fopen('php://memory', 'w') ?: null);

        $this->assertSame('serve', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertStringContainsString('ez serve', $command->getHelp());
    }

    /**
     * @return void
     */
    public function test_server_command_uses_the_running_php_binary(): void
    {
        $command = new ServeCommand('/var/www/public', errorStream: fopen('php://memory', 'w') ?: null);

        $this->assertSame(
            escapeshellarg(PHP_BINARY) . " -S 'localhost:8000' -t '/var/www/public'",
            $command->serverCommand('localhost:8000'),
        );
    }

    /**
     * @return void
     */
    public function test_server_command_escapes_the_address_and_path(): void
    {
        $command = new ServeCommand("/srv/my app's/public", errorStream: fopen('php://memory', 'w') ?: null);

        $this->assertStringEndsWith(
            ' -S ' . escapeshellarg('0.0.0.0:80; rm -rf /') . ' -t ' . escapeshellarg("/srv/my app's/public"),
            $command->serverCommand('0.0.0.0:80; rm -rf /'),
        );
    }

    /**
     * @return void
     */
    public function test_serves_on_default_host_and_port_and_returns_the_server_exit_code(): void
    {
        $command = new ServeCommand($this->base . '/public', $this->fakeBinary(exitCode: 3), errorStream: fopen('php://memory', 'w') ?: null);

        [$code, $output] = $this->serve($command, []);

        $this->assertSame(3, $code);
        $this->assertStringContainsString('Starting server at http://localhost:8000', $output);
        $this->assertStringNotContainsString('--watch', $output);
        $this->assertSame(['-S localhost:8000 -t ' . $this->base . '/public'], $this->calls());
    }

    /**
     * @return void
     */
    public function test_host_and_port_options_are_passed_to_the_server(): void
    {
        $command = new ServeCommand($this->base . '/public', $this->fakeBinary(), errorStream: fopen('php://memory', 'w') ?: null);

        [$code, $output] = $this->serve($command, ['--host=0.0.0.0', '--port=9123']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('http://0.0.0.0:9123', $output);
        $this->assertSame(['-S 0.0.0.0:9123 -t ' . $this->base . '/public'], $this->calls());
    }

    /**
     * @return void
     */
    public function test_watch_mode_lists_existing_directories_and_returns_when_the_server_stops(): void
    {
        mkdir($this->base . '/app');
        mkdir($this->base . '/routes');
        file_put_contents($this->base . '/app/Foo.php', '<?php');
        $command = new ServeCommand($this->base . '/public', $this->fakeBinary(exitCode: 1), errorStream: fopen('php://memory', 'w') ?: null);

        [$code, $output] = $this->serve($command, ['--watch']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('(--watch)', $output);
        $this->assertStringContainsString('Watching: app/, routes/', $output);
        $this->assertCount(1, $this->calls());
    }

    /**
     * @return void
     */
    public function test_watch_mode_restarts_the_server_when_a_php_file_changes(): void
    {
        mkdir($this->base . '/config');
        $file = $this->base . '/config/app.php';
        file_put_contents($file, '<?php');
        touch($file, time() - 60);

        // The first server run lasts 4 s; a background job changes the file after 1 s,
        // so the next poll sees a new mtime and restarts. The restarted run exits at once.
        exec('(sleep 1; touch ' . escapeshellarg($file) . ') > /dev/null 2>&1 &');
        $command = new ServeCommand($this->base . '/public', $this->fakeBinary(firstSleep: 4), errorStream: fopen('php://memory', 'w') ?: null);

        [$code, $output] = $this->serve($command, ['--watch', '--port=9124']);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('[watch] Change detected', $output);
        $this->assertCount(2, $this->calls());
    }

    /**
     * @return void
     */
    public function test_watch_mode_returns_1_when_the_server_cannot_start(): void
    {
        $command = new ServeCommand($this->base . '/public', $this->base . '/missing-php', errorStream: fopen('php://memory', 'w') ?: null);

        [$code] = $this->serve($command, ['--watch']);

        $this->assertSame(1, $code);
    }
}
