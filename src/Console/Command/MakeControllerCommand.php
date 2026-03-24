<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeControllerCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeControllerCommand implements CommandInterface
{
    /**
     * MakeControllerCommand Constructor
     *
     * @param string $srcPath
     */
    public function __construct(private string $srcPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:controller';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new controller class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:controller <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:controller <ClassName>\n");
            return 1;
        }

        $dir = $this->srcPath . DIRECTORY_SEPARATOR . 'Controllers';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Controller already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create controller: $filename\n");
            return 1;
        }

        echo "Created: src/Controllers/$filename\n";

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

            namespace App\\Controllers;

            use EzPhp\\Http\\Request;
            use EzPhp\\Http\\Response;

            final class $name
            {
                public function index(Request \$request): Response
                {
                    return new Response('');
                }
            }
            PHP;
    }
}
