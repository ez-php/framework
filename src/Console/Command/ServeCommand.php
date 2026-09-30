<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;

/**
 * Class ServeCommand
 *
 * Starts the built-in PHP web server pointing at the application's public/ directory.
 *
 * When --watch is passed the server restarts automatically whenever a .php file
 * changes under app/, config/, or routes/ relative to the application root.
 * The watch loop polls once per second using filemtime; no OS-specific inotify
 * dependency is required.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class ServeCommand implements CommandInterface
{
    /**
     * ServeCommand Constructor
     *
     * @param string $publicPath Absolute path to the public/ directory.
     * @param string $phpBinary  Interpreter that runs the server; defaults to the one running this command.
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private string $publicPath,
        private string $phpBinary = PHP_BINARY,
        private mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'serve';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Start the built-in PHP web server';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez serve [--host=localhost] [--port=8000] [--watch]\n\nOptions:\n  --host   Hostname to listen on (default: localhost)\n  --port   Port number (default: 8000)\n  --watch  Restart the server when PHP files change in app/, config/, routes/";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);
        $host = $input->option('host', 'localhost');
        $port = $input->option('port', '8000');
        $address = "$host:$port";

        if (!$input->hasFlag('watch')) {
            $this->announce($address, false);
            echo "Press Ctrl+C to stop.\n";
            passthru($this->serverCommand($address), $exitCode);
            return (int) $exitCode;
        }

        return $this->serveWithWatch($address);
    }

    /**
     * Start the PHP server in a subprocess and restart it whenever a watched
     * PHP file changes.
     *
     * @param string $address  host:port to bind to.
     *
     * @return int 0 when the server stops, 1 when it cannot be (re)started.
     */
    private function serveWithWatch(string $address): int
    {
        $basePath = dirname($this->publicPath);
        $watchDirs = array_values(array_filter(
            [$basePath . '/app', $basePath . '/config', $basePath . '/routes'],
            static fn (string $dir): bool => is_dir($dir),
        ));

        $this->announce($address, true);
        echo 'Watching: ' . implode(', ', array_map(
            static fn (string $d): string => basename($d) . '/',
            $watchDirs,
        )) . "\n";
        echo "Press Ctrl+C to stop.\n\n";

        $mtimes = $this->collectMtimes($watchDirs);
        $process = $this->spawnServer($address);

        if ($process === null) {
            return 1;
        }

        while (proc_get_status($process)['running']) {
            sleep(1);

            $current = $this->collectMtimes($watchDirs);

            if ($current !== $mtimes) {
                $mtimes = $current;
                echo '[watch] Change detected — restarting...' . "\n";
                proc_terminate($process);
                proc_close($process);
                $process = $this->spawnServer($address);

                if ($process === null) {
                    return 1;
                }
            }
        }

        proc_close($process);

        return 0;
    }

    /**
     * Print the "Starting server at ..." banner line.
     *
     * @param string $address host:port being bound to.
     * @param bool   $watch   Whether --watch mode is active.
     *
     * @return void
     */
    private function announce(string $address, bool $watch): void
    {
        echo "Starting server at http://$address" . ($watch ? ' (--watch)' : '') . "\n";
    }

    /**
     * Build the shell command that starts PHP's built-in server.
     *
     * Uses the interpreter running this command (PHP_BINARY by default), not whatever
     * `php` is first on PATH — on hosts with several PHP versions that could be another one.
     *
     * @param string $address host:port to bind to.
     *
     * @return string
     */
    public function serverCommand(string $address): string
    {
        return escapeshellarg($this->phpBinary) . ' -S ' . escapeshellarg($address) . ' -t ' . escapeshellarg($this->publicPath);
    }

    /**
     * Spawn a PHP built-in server subprocess and return the process handle.
     *
     * @param string $address
     *
     * @return resource|null Null when the process could not be started (reported on STDERR).
     */
    private function spawnServer(string $address): mixed
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ];
        $pipes = [];
        // Array form: no shell in between, so a missing interpreter makes proc_open()
        // fail here instead of spawning a shell that exits with 127.
        $process = @proc_open([$this->phpBinary, '-S', $address, '-t', $this->publicPath], $descriptors, $pipes);

        if ($process === false) {
            $error = error_get_last()['message'] ?? 'unknown error';
            fwrite($this->errorStream ?? STDERR, "Failed to start PHP server: $error\n");

            return null;
        }

        return $process;
    }

    /**
     * Collect filemtime for every *.php file under the given directories.
     *
     * @param list<string> $dirs
     *
     * @return array<string, int>
     */
    private function collectMtimes(array $dirs): array
    {
        // PHP caches the last stat() result; without this, a change to the most
        // recently stat'ed file (e.g. the only watched file) is never seen.
        clearstatcache();

        $map = [];

        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if ($file->getExtension() === 'php') {
                    $map[$file->getPathname()] = $file->getMTime();
                }
            }
        }

        ksort($map);

        return $map;
    }
}
