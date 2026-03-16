<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Exceptions\RouteException;
use EzPhp\Middleware\MiddlewareHandler;
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
 * Class ExceptionHandlerServiceProviderTest
 *
 * @package Tests\Exceptions
 */
#[CoversClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(MiddlewareHandler::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(RouteException::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
final class ExceptionHandlerServiceProviderTest extends DatabaseTestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_register_binds_exception_handler_into_container(): void
    {
        $handler = $this->app()->make(ExceptionHandler::class);

        $this->assertInstanceOf(DefaultExceptionHandler::class, $handler);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_exception_handler_resolves_as_singleton(): void
    {
        $h1 = $this->app()->make(ExceptionHandler::class);
        $h2 = $this->app()->make(ExceptionHandler::class);

        $this->assertSame($h1, $h2);
    }
}
