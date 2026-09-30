<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeListenerCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeListenerCommand implements CommandInterface
{
    /**
     * MakeListenerCommand Constructor
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
        return 'make:listener';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new event listener class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:listener <ClassName>';
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
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:listener <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Listeners';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Listener already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create listener: $filename\n");
            return 1;
        }

        echo "Created: app/Listeners/$filename\n";

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

            namespace App\\Listeners;

            use EzPhp\\Events\\EventInterface;
            use EzPhp\\Events\\ListenerInterface;

            final class $name implements ListenerInterface
            {
                public function handle(EventInterface \$event): void
                {
                }
            }
            PHP;
    }
}
