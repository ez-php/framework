<?php

declare(strict_types=1);

namespace Tests\Routing;

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
use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
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
use Tests\TestCase;

/**
 * Class RouterServiceProviderTest
 *
 * @package Tests\Routing
 */
#[CoversClass(RouterServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(MiddlewareHandler::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(RouteException::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
final class RouterServiceProviderTest extends TestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_register_binds_router_into_container(): void
    {
        $app = new Application();
        $app->bootstrap();

        $router = $app->make(Router::class);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_router_resolves_as_singleton(): void
    {
        $app = new Application();
        $app->bootstrap();

        $r1 = $app->make(Router::class);
        $r2 = $app->make(Router::class);

        $this->assertSame($r1, $r2);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_routes_from_web_php_are_loaded(): void
    {
        $app = new Application();
        $app->bootstrap();

        $response = $app->handle(new Request('GET', '/'));

        $this->assertSame(200, $response->status());
        $this->assertSame('Hello from ez-php!', $response->body());
    }
}
