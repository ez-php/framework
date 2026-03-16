<?php

declare(strict_types=1);

namespace Tests\Database;

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
 * Class DatabaseServiceProviderTest
 *
 * @package Tests\Database
 */
#[CoversClass(DatabaseServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
final class DatabaseServiceProviderTest extends DatabaseTestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_register_binds_database_into_container(): void
    {
        $db = $this->app()->make(Database::class);

        $this->assertInstanceOf(Database::class, $db);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_database_connection_is_functional(): void
    {
        $db = $this->app()->make(Database::class);
        $result = $db->query('SELECT 1 as value');

        $this->assertSame(1, $result[0]['value']);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_database_resolves_as_singleton(): void
    {
        $db1 = $this->app()->make(Database::class);
        $db2 = $this->app()->make(Database::class);

        $this->assertSame($db1, $db2);
    }
}
