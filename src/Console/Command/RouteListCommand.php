<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Routing\Router;

/**
 * Class RouteListCommand
 *
 * Prints every registered route (method, path, name, middleware) as a table.
 *
 * Usage:
 *   ez route:list
 *
 * Unlike route:cache, this lists closure-handler routes too — it is a
 * read-only introspection command, not a cache builder.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final class RouteListCommand implements CommandInterface
{
    /**
     * RouteListCommand Constructor
     *
     * @param Router $router The router instance with all routes already loaded.
     */
    public function __construct(
        private readonly Router $router,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'route:list';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'List all registered routes';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez route:list\n\n"
            . 'Prints every registered route (method, path, name, middleware) as a table.';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $routes = $this->router->all();

        if ($routes === []) {
            Output::warning('No routes registered.');

            return 0;
        }

        $rows = [];

        foreach ($routes as $route) {
            $rows[] = [
                $route['method'],
                $route['path'],
                $route['name'] ?? '',
                implode(', ', $route['middleware']),
            ];
        }

        Output::table(['Method', 'Path', 'Name', 'Middleware'], $rows);

        return 0;
    }
}
