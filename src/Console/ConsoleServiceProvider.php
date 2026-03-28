<?php

declare(strict_types=1);

namespace EzPhp\Console;

use EzPhp\Application\Application;
use EzPhp\Config\ConfigLoader;
use EzPhp\Console\Command\ConfigCacheCommand;
use EzPhp\Console\Command\ConfigClearCommand;
use EzPhp\Console\Command\DoctorCommand;
use EzPhp\Console\Command\EnvCheckCommand;
use EzPhp\Console\Command\IdeGenerateCommand;
use EzPhp\Console\Command\ListCommand;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeChannelCommand;
use EzPhp\Console\Command\MakeEventCommand;
use EzPhp\Console\Command\MakeJobCommand;
use EzPhp\Console\Command\MakeListenerCommand;
use EzPhp\Console\Command\MakeNotificationCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeModelCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MakeRequestCommand;
use EzPhp\Console\Command\MakeTestCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateFreshCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\Command\MigrateStatusCommand;
use EzPhp\Console\Command\ScheduleRunCommand;
use EzPhp\Console\Command\ServeCommand;
use EzPhp\Console\Command\TinkerCommand;
use EzPhp\Console\Schedule\Scheduler;
use EzPhp\Migration\Migrator;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class ConsoleServiceProvider
 *
 * @internal
 * @package EzPhp\Console
 */
final class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Scheduler::class, function (): Scheduler {
            return new Scheduler();
        });

        $this->app->bind(ScheduleRunCommand::class, function (Application $app): ScheduleRunCommand {
            return new ScheduleRunCommand(
                $app->make(Scheduler::class),
                fn (): Console => $app->make(Console::class),
            );
        });

        $this->app->bind(MigrateCommand::class, function (Application $app): MigrateCommand {
            return new MigrateCommand($app->make(Migrator::class));
        });

        $this->app->bind(MigrateRollbackCommand::class, function (Application $app): MigrateRollbackCommand {
            return new MigrateRollbackCommand($app->make(Migrator::class));
        });

        $this->app->bind(MigrateFreshCommand::class, function (Application $app): MigrateFreshCommand {
            return new MigrateFreshCommand($app->make(Migrator::class));
        });

        $this->app->bind(MigrateStatusCommand::class, function (Application $app): MigrateStatusCommand {
            return new MigrateStatusCommand($app->make(Migrator::class));
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

        $this->app->bind(MakeModelCommand::class, function (Application $app): MakeModelCommand {
            return new MakeModelCommand($app->basePath('app'));
        });

        $this->app->bind(MakeEventCommand::class, function (Application $app): MakeEventCommand {
            return new MakeEventCommand($app->basePath('app'));
        });

        $this->app->bind(MakeListenerCommand::class, function (Application $app): MakeListenerCommand {
            return new MakeListenerCommand($app->basePath('app'));
        });

        $this->app->bind(MakeJobCommand::class, function (Application $app): MakeJobCommand {
            return new MakeJobCommand($app->basePath('app'));
        });

        $this->app->bind(MakeNotificationCommand::class, function (Application $app): MakeNotificationCommand {
            return new MakeNotificationCommand($app->basePath('app'));
        });

        $this->app->bind(MakeChannelCommand::class, function (Application $app): MakeChannelCommand {
            return new MakeChannelCommand($app->basePath('app'));
        });

        $this->app->bind(MakeRequestCommand::class, function (Application $app): MakeRequestCommand {
            return new MakeRequestCommand($app->basePath('app'));
        });

        $this->app->bind(MakeTestCommand::class, function (Application $app): MakeTestCommand {
            return new MakeTestCommand($app->basePath('tests'));
        });

        $this->app->bind(ServeCommand::class, function (Application $app): ServeCommand {
            return new ServeCommand($app->basePath('public'));
        });

        $this->app->bind(TinkerCommand::class, function (Application $app): TinkerCommand {
            return new TinkerCommand($app);
        });

        $this->app->bind(EnvCheckCommand::class, function (Application $app): EnvCheckCommand {
            return new EnvCheckCommand($app->basePath('.env.example'));
        });

        $this->app->bind(DoctorCommand::class, function (Application $app): DoctorCommand {
            return new DoctorCommand($app->basePath('.env.example'));
        });

        $this->app->bind(ConfigCacheCommand::class, function (Application $app): ConfigCacheCommand {
            return new ConfigCacheCommand(
                $app->make(ConfigLoader::class),
                $app->basePath('bootstrap/cache/config.php'),
            );
        });

        $this->app->bind(ConfigClearCommand::class, function (Application $app): ConfigClearCommand {
            return new ConfigClearCommand($app->basePath('bootstrap/cache/config.php'));
        });

        $this->app->bind(IdeGenerateCommand::class, function (Application $app): IdeGenerateCommand {
            return new IdeGenerateCommand($app->basePath());
        });

        $this->app->bind(Console::class, function (Application $app): Console {
            /** @var list<CommandInterface> $commands */
            $commands = [
                $app->make(ConfigCacheCommand::class),
                $app->make(ConfigClearCommand::class),
                $app->make(ServeCommand::class),
                $app->make(MigrateCommand::class),
                $app->make(MigrateRollbackCommand::class),
                $app->make(MigrateFreshCommand::class),
                $app->make(MigrateStatusCommand::class),
                $app->make(MakeMigrationCommand::class),
                $app->make(MakeControllerCommand::class),
                $app->make(MakeMiddlewareCommand::class),
                $app->make(MakeProviderCommand::class),
                $app->make(MakeModelCommand::class),
                $app->make(MakeEventCommand::class),
                $app->make(MakeListenerCommand::class),
                $app->make(MakeJobCommand::class),
                $app->make(MakeNotificationCommand::class),
                $app->make(MakeChannelCommand::class),
                $app->make(MakeRequestCommand::class),
                $app->make(MakeTestCommand::class),
                $app->make(TinkerCommand::class),
                $app->make(EnvCheckCommand::class),
                $app->make(DoctorCommand::class),
                $app->make(IdeGenerateCommand::class),
            ];

            foreach ($app->getCommands() as $class) {
                /** @var CommandInterface $userCommand */
                $userCommand = $app->make($class);
                $commands[] = $userCommand;
            }

            $commands[] = $app->make(ScheduleRunCommand::class);

            $listCommand = new ListCommand($commands);

            return new Console([$listCommand, ...$commands]);
        });
    }
}
