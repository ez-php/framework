<?php

declare(strict_types=1);

namespace EzPhp\Console;

use EzPhp\Application\Application;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Migration\Migrator;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class ConsoleServiceProvider
 *
 * @package EzPhp\Console
 */
final class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MigrateCommand::class, function (Application $app): MigrateCommand {
            return new MigrateCommand($app->make(Migrator::class));
        });

        $this->app->bind(MigrateRollbackCommand::class, function (Application $app): MigrateRollbackCommand {
            return new MigrateRollbackCommand($app->make(Migrator::class));
        });

        $this->app->bind(MakeMigrationCommand::class, function (Application $app): MakeMigrationCommand {
            return new MakeMigrationCommand($app->basePath('database/migrations'));
        });

        $this->app->bind(MakeControllerCommand::class, function (Application $app): MakeControllerCommand {
            return new MakeControllerCommand($app->basePath('src'));
        });

        $this->app->bind(MakeMiddlewareCommand::class, function (Application $app): MakeMiddlewareCommand {
            return new MakeMiddlewareCommand($app->basePath('src'));
        });

        $this->app->bind(MakeProviderCommand::class, function (Application $app): MakeProviderCommand {
            return new MakeProviderCommand($app->basePath('src'));
        });

        $this->app->bind(Console::class, function (Application $app): Console {
            return new Console([
                $app->make(MigrateCommand::class),
                $app->make(MigrateRollbackCommand::class),
                $app->make(MakeMigrationCommand::class),
                $app->make(MakeControllerCommand::class),
                $app->make(MakeMiddlewareCommand::class),
                $app->make(MakeProviderCommand::class),
            ]);
        });
    }
}
