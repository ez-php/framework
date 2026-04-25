<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Routing\Router;

/**
 * Class RouteCacheCommand
 *
 * Serializes all array-handler routes into a single PHP file so that the
 * application can skip loading and parsing routes/web.php on every request.
 *
 * Usage:
 *   ez route:cache
 *
 * The cache is written to `bootstrap/cache/routes.php` relative to the
 * application root. The directory is created automatically if absent.
 *
 * Only routes registered with [Controller::class, 'method'] array handlers
 * can be cached. Routes using closures are silently skipped. If no cacheable
 * routes are found, a warning is printed and the file is not written.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final class RouteCacheCommand implements CommandInterface
{
    /**
     * RouteCacheCommand Constructor
     *
     * @param Router $router    The router instance with all routes already loaded.
     * @param string $cachePath Absolute path to the cache file.
     */
    public function __construct(
        private readonly Router $router,
        private readonly string $cachePath,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'route:cache';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Cache all routes into a compiled file for faster production dispatch';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez route:cache\n\n"
            . "Serializes all registered routes into bootstrap/cache/routes.php.\n"
            . "Only routes with [Controller::class, 'method'] handlers can be cached;\n"
            . "closure-based routes are skipped.\n"
            . 'Clear the cache with: ez route:clear';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $data = $this->router->toCache();

        if ($data === []) {
            Output::warning(
                'No cacheable routes found. Only routes with array handlers '
                . "([Controller::class, 'method']) can be cached."
            );

            return 0;
        }

        $dir = dirname($this->cachePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
            Output::error("Could not create cache directory: $dir");

            return 1;
        }

        $content = '<?php return ' . var_export($data, true) . ';' . "\n";

        if (file_put_contents($this->cachePath, $content) === false) {
            Output::error("Could not write route cache: {$this->cachePath}");

            return 1;
        }

        Output::success('Routes cached successfully.');

        return 0;
    }
}
