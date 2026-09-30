<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeEventCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeEventCommand implements CommandInterface
{
    /**
     * MakeEventCommand Constructor
     *
     * @param string $appPath
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private string $appPath,
        private mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:event';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new event class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:event <ClassName>';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:event <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Events';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Event already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create event: $filename\n");
            return 1;
        }

        echo "Created: app/Events/$filename\n";

        return 0;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Events;

            use EzPhp\\Events\\EventInterface;

            final class $name implements EventInterface
            {
                public function __construct()
                {
                }
            }
            PHP;
    }
}
