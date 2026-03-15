<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
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
#[UsesClass(MakeMigrationCommand::class)]
#[UsesClass(MakeControllerCommand::class)]
#[UsesClass(MakeMiddlewareCommand::class)]
#[UsesClass(MakeProviderCommand::class)]
final class ConsoleServiceProviderTest extends DatabaseTestCase
{
    /**
     * @throws ReflectionException
     */
    public function test_console_is_bound_in_container(): void
    {
        $app = new Application();
        $app->bootstrap();

        $console = $app->make(Console::class);

        $this->assertInstanceOf(Console::class, $console);
    }

    /**
     * @throws ReflectionException
     */
    public function test_all_core_commands_are_registered(): void
    {
        $app = new Application();
        $app->bootstrap();

        $this->assertInstanceOf(MigrateCommand::class, $app->make(MigrateCommand::class));
        $this->assertInstanceOf(MigrateRollbackCommand::class, $app->make(MigrateRollbackCommand::class));
        $this->assertInstanceOf(MakeMigrationCommand::class, $app->make(MakeMigrationCommand::class));
        $this->assertInstanceOf(MakeControllerCommand::class, $app->make(MakeControllerCommand::class));
        $this->assertInstanceOf(MakeMiddlewareCommand::class, $app->make(MakeMiddlewareCommand::class));
        $this->assertInstanceOf(MakeProviderCommand::class, $app->make(MakeProviderCommand::class));
    }

    /**
     * @throws ReflectionException
     */
    public function test_console_knows_core_commands(): void
    {
        $app = new Application();
        $app->bootstrap();

        $console = $app->make(Console::class);

        ob_start();
        $console->run(['ez']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('migrate', $output);
        $this->assertStringContainsString('migrate:rollback', $output);
        $this->assertStringContainsString('make:migration', $output);
        $this->assertStringContainsString('make:controller', $output);
        $this->assertStringContainsString('make:middleware', $output);
        $this->assertStringContainsString('make:provider', $output);
    }
}
