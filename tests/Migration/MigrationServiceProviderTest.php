<?php

declare(strict_types=1);

namespace Tests\Migration;

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
use Throwable;

/**
 * Class MigrationServiceProviderTest
 *
 * @package Tests\Migration
 */
#[CoversClass(MigrationServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
final class MigrationServiceProviderTest extends DatabaseTestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_register_binds_migrator_into_container(): void
    {
        $migrator = $this->app()->make(Migrator::class);

        $this->assertInstanceOf(Migrator::class, $migrator);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_migrator_resolves_as_singleton(): void
    {
        $m1 = $this->app()->make(Migrator::class);
        $m2 = $this->app()->make(Migrator::class);

        $this->assertSame($m1, $m2);
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws Throwable
     */
    public function test_migrator_can_run_against_real_database(): void
    {
        $migrator = $this->app()->make(Migrator::class);

        // database/migrations/ is empty — nothing to run, no error
        $ran = $migrator->migrate();

        $this->assertSame([], $ran);

        // CREATE TABLE is DDL and causes an implicit MySQL commit, bypassing the
        // outer transaction. Drop the migrations table explicitly for cleanup.
        $this->app()->make(Database::class)->query('DROP TABLE IF EXISTS migrations');
    }
}
