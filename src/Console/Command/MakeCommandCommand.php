<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeCommandCommand
 *
 * Scaffolds a console command implementing `EzPhp\Console\CommandInterface`.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeCommandCommand implements CommandInterface
{
    /**
     * MakeCommandCommand Constructor
     *
     * @param string $appPath Application source root. `ConsoleServiceProvider` passes
     *                        `basePath('app')` — the template autoloads `App\ => app/`
     *                        and ships no `src/` directory. Every other `make:*`
     *                        generator that emits an `App\…` class is bound the
     *                        same way.
     */
    public function __construct(private string $appPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:command';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new console command class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:command <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:command <ClassName>\n");

            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Console';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Command already exists: $filename\n");

            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create command: $filename\n");

            return 1;
        }

        echo "Created: app/Console/$filename\n";

        return 0;
    }

    /**
     * Derive a default console name from the class name.
     *
     * `SyncUsersCommand` becomes `sync-users`. A concrete, working default beats a
     * placeholder the user has to replace before the command can run at all — and
     * it stays obvious and editable, with no lookup table or runtime magic.
     *
     * @param string $className
     *
     * @return string
     */
    private function commandName(string $className): string
    {
        $base = preg_replace('/Command$/', '', $className) ?? $className;

        if ($base === '') {
            $base = $className;
        }

        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $base) ?? $base;

        return strtolower($kebab);
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
    {
        $commandName = $this->commandName($name);

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Console;

            use EzPhp\\Console\\CommandInterface;

            /**
             * Register this command from a service provider's boot():
             *
             *     if (\$this->app instanceof CommandRegistryInterface) {
             *         \$this->app->registerCommand($name::class);
             *     }
             *
             * The instanceof guard is needed because registerCommand() lives on
             * EzPhp\\Contracts\\CommandRegistryInterface, which Application implements
             * but ContainerInterface does not declare.
             */
            final class $name implements CommandInterface
            {
                public function getName(): string
                {
                    return '$commandName';
                }

                public function getDescription(): string
                {
                    return '';
                }

                public function getHelp(): string
                {
                    return 'Usage: ez $commandName';
                }

                /**
                 * @param list<string> \$args
                 */
                public function handle(array \$args): int
                {
                    return 0;
                }
            }
            PHP;
    }
}
