<?php

declare(strict_types=1);

namespace EzPhp\ServiceProvider;

use EzPhp\Application\Application;
use EzPhp\Contracts\ServiceProvider as ContractsServiceProvider;

/**
 * Class ServiceProvider
 *
 * Framework-internal base class for core service providers.
 * Extends the contracts ServiceProvider and adds a basePath() helper
 * for providers that need to resolve file-system paths relative to the
 * application root (e.g. config/, routes/, database/migrations/).
 *
 * @package EzPhp\ServiceProvider
 */
abstract class ServiceProvider extends ContractsServiceProvider
{
    /**
     * Resolve a path relative to the application root.
     *
     * @param string $path Optional sub-path to append.
     *
     * @return string
     */
    protected function basePath(string $path = ''): string
    {
        /** @var Application $app */
        $app = $this->app;

        return $app->basePath($path);
    }
}
