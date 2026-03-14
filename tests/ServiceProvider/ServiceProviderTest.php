<?php

declare(strict_types=1);

namespace Tests\ServiceProvider;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\Console;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * @internal test helper
 */
final class TrackingServiceProvider extends ServiceProvider
{
    public bool $registered = false;

    public bool $booted = false;

    /**
     * @return void
     */
    public function register(): void
    {
        $this->registered = true;
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        $this->booted = true;
    }
}

/**
 * Class ServiceProviderTest
 *
 * @package Tests\ServiceProvider
 */
#[CoversClass(ServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(Console::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
final class ServiceProviderTest extends TestCase
{
    /**
     * @return TrackingServiceProvider
     * @throws ApplicationException
     * @throws ContainerException
     */
    private function makeProvider(): TrackingServiceProvider
    {
        $app = new Application();
        $app->bootstrap();

        return new TrackingServiceProvider($app);
    }

    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_register_is_callable(): void
    {
        $provider = $this->makeProvider();
        $provider->register();
        $this->assertTrue($provider->registered);
    }

    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_boot_is_callable(): void
    {
        $provider = $this->makeProvider();
        $provider->boot();
        $this->assertTrue($provider->booted);
    }

    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_base_register_does_nothing(): void
    {
        $app = new Application();
        $app->bootstrap();
        $provider = new class ($app) extends ServiceProvider {};
        $provider->register();
        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_base_boot_does_nothing(): void
    {
        $app = new Application();
        $app->bootstrap();
        $provider = new class ($app) extends ServiceProvider {};
        $provider->boot();
        $this->expectNotToPerformAssertions();
    }
}
