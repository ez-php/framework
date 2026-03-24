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
        return "Usage: ez serve [--host=localhost] [--port=8000]\n\nOptions:\n  --host   Hostname to listen on (default: localhost)\n  --port   Port number (default: 8000)";
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

        echo "Starting server at http://$address\n";
        echo "Press Ctrl+C to stop.\n";

        passthru('php -S ' . escapeshellarg($address) . ' -t ' . escapeshellarg($this->publicPath), $exitCode);

        return (int) $exitCode;
    }
}
