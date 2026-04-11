<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Application\Application;
use EzPhp\Database\Database;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class MigrationServiceProvider
 *
 * @internal
 * @package EzPhp\Migration
 */
final class MigrationServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Migrator::class, function (Application $app): Migrator {
            return new Migrator(
                $app->make(Database::class),
                $app->basePath('database/migrations'),
            );
        });

        $this->app->bind(SeederRunner::class, function (Application $app): SeederRunner {
            return new SeederRunner(
                $app->make(Database::class),
                $app->basePath('database/seeders'),
            );
        });
    }
}
