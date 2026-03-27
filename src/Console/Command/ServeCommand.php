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
     */
    public function __construct(private string $publicPath)
    {
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
            echo "Starting server at http://$address\n";
            echo "Press Ctrl+C to stop.\n";
            passthru('php -S ' . escapeshellarg($address) . ' -t ' . escapeshellarg($this->publicPath), $exitCode);
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
     * @return int Always 0 (exits via Ctrl+C).
     */
    private function serveWithWatch(string $address): int
    {
        $basePath = dirname($this->publicPath);
        $watchDirs = array_values(array_filter(
            [$basePath . '/app', $basePath . '/config', $basePath . '/routes'],
            static fn (string $dir): bool => is_dir($dir),
        ));

        echo "Starting server at http://$address (--watch)\n";
        echo 'Watching: ' . implode(', ', array_map(
            static fn (string $d): string => basename($d) . '/',
            $watchDirs,
        )) . "\n";
        echo "Press Ctrl+C to stop.\n\n";

        $mtimes = $this->collectMtimes($watchDirs);
        $process = $this->spawnServer($address);

        while (proc_get_status($process)['running']) {
            sleep(1);

            $current = $this->collectMtimes($watchDirs);

            if ($current !== $mtimes) {
                $mtimes = $current;
                echo '[watch] Change detected — restarting...' . "\n";
                proc_terminate($process);
                proc_close($process);
                $process = $this->spawnServer($address);
            }
        }

        proc_close($process);

        return 0;
    }

    /**
     * Spawn a PHP built-in server subprocess and return the process handle.
     *
     * @param string $address
     *
     * @return resource
     */
    private function spawnServer(string $address): mixed
    {
        $cmd = 'php -S ' . escapeshellarg($address) . ' -t ' . escapeshellarg($this->publicPath);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => STDOUT,
            2 => STDERR,
        ];
        $pipes = [];
        $process = proc_open($cmd, $descriptors, $pipes);

        if ($process === false) {
            fwrite(STDERR, "Failed to start PHP server.\n");
            exit(1);
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
