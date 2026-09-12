<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeMiddlewareCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeMiddlewareCommand implements CommandInterface
{
    /**
     * MakeMiddlewareCommand Constructor
     *
     * @param string $appPath Application source root. `ConsoleServiceProvider` passes
     *                        `basePath('app')`: the stub declares `namespace App\Middleware;`
     *                        and the template autoloads `App\ => app/`, so anywhere else
     *                        produces a class Composer never loads.
     */
    public function __construct(private string $appPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:middleware';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new middleware class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:middleware <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:middleware <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Middleware';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Middleware already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create middleware: $filename\n");
            return 1;
        }

        echo "Created: app/Middleware/$filename\n";

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

            namespace App\\Middleware;

            use EzPhp\\Http\\RequestInterface;
            use EzPhp\\Http\\ResponseInterface;
            use EzPhp\\Middleware\\MiddlewareInterface;

            final class $name implements MiddlewareInterface
            {
                public function handle(RequestInterface \$request, callable \$next): ResponseInterface
                {
                    /** @var ResponseInterface */
                    return \$next(\$request);
                }
            }
            PHP;
    }
}
