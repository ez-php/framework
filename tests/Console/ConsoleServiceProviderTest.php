<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\EnvCheckCommand;
use EzPhp\Console\Command\ListCommand;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateFreshCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\Command\MigrateStatusCommand;
use EzPhp\Console\Command\ServeCommand;
use EzPhp\Console\Command\TinkerCommand;
use EzPhp\Console\Console;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Migration\Migrator;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\DatabaseTestCase;

/**
 * Class ConsoleServiceProviderTest
 *
 * @package Tests\Console
 */
#[CoversClass(ConsoleServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MigrateFreshCommand::class)]
#[UsesClass(MigrateStatusCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
#[UsesClass(MakeControllerCommand::class)]
#[UsesClass(MakeMiddlewareCommand::class)]
#[UsesClass(MakeProviderCommand::class)]
#[UsesClass(ServeCommand::class)]
#[UsesClass(TinkerCommand::class)]
#[UsesClass(EnvCheckCommand::class)]
#[UsesClass(ListCommand::class)]
final class ConsoleServiceProviderTest extends DatabaseTestCase
{
    /**
     * @throws ReflectionException
     */
    public function test_console_is_bound_in_container(): void
    {
        $console = $this->app()->make(Console::class);

        $this->assertInstanceOf(Console::class, $console);
    }

    /**
     * @throws ReflectionException
     */
    public function test_all_core_commands_are_registered(): void
    {
        $this->assertInstanceOf(MigrateCommand::class, $this->app()->make(MigrateCommand::class));
        $this->assertInstanceOf(MigrateRollbackCommand::class, $this->app()->make(MigrateRollbackCommand::class));
        $this->assertInstanceOf(MigrateFreshCommand::class, $this->app()->make(MigrateFreshCommand::class));
        $this->assertInstanceOf(MigrateStatusCommand::class, $this->app()->make(MigrateStatusCommand::class));
        $this->assertInstanceOf(MakeMigrationCommand::class, $this->app()->make(MakeMigrationCommand::class));
        $this->assertInstanceOf(MakeControllerCommand::class, $this->app()->make(MakeControllerCommand::class));
        $this->assertInstanceOf(MakeMiddlewareCommand::class, $this->app()->make(MakeMiddlewareCommand::class));
        $this->assertInstanceOf(MakeProviderCommand::class, $this->app()->make(MakeProviderCommand::class));
        $this->assertInstanceOf(ServeCommand::class, $this->app()->make(ServeCommand::class));
        $this->assertInstanceOf(TinkerCommand::class, $this->app()->make(TinkerCommand::class));
    }

    /**
     * @throws ReflectionException
     */
    public function test_console_knows_core_commands(): void
    {
        $console = $this->app()->make(Console::class);

        ob_start();
        $console->run(['ez']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('list', $output);
        $this->assertStringContainsString('serve', $output);
        $this->assertStringContainsString('migrate', $output);
        $this->assertStringContainsString('migrate:rollback', $output);
        $this->assertStringContainsString('migrate:fresh', $output);
        $this->assertStringContainsString('migrate:status', $output);
        $this->assertStringContainsString('make:migration', $output);
        $this->assertStringContainsString('make:controller', $output);
        $this->assertStringContainsString('make:middleware', $output);
        $this->assertStringContainsString('make:provider', $output);
        $this->assertStringContainsString('tinker', $output);
    }
}
