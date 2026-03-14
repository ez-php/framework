<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeProviderCommand
 *
 * @package EzPhp\Console\Command
 */
final class MakeProviderCommand implements CommandInterface
{
    /**
     * MakeProviderCommand Constructor
     *
     * @param string $srcPath
     */
    public function __construct(private readonly string $srcPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:provider';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new service provider class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:provider <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:provider <ClassName>\n");
            return 1;
        }

        $dir = $this->srcPath . DIRECTORY_SEPARATOR . 'Providers';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Provider already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create provider: $filename\n");
            return 1;
        }

        echo "Created: src/Providers/$filename\n";

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

            namespace App\\Providers;

            use EzPhp\\ServiceProvider\\ServiceProvider;

            final class $name extends ServiceProvider
            {
                public function register(): void
                {
                    //
                }
            }
            PHP;
    }
}
