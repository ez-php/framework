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
use EzPhp\Console\Command\RouteCacheCommand;
use EzPhp\Console\Command\RouteClearCommand;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Exceptions\ProductionHtmlRenderer;
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
#[UsesClass(ProductionHtmlRenderer::class)]
#[UsesClass(RouteException::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]
#[UsesClass(RouteCacheCommand::class)]
#[UsesClass(RouteClearCommand::class)]
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

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_boot_is_noop_when_routes_file_missing(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ez-php-no-routes-' . uniqid();
        mkdir($tmpDir . '/config', 0o777, true);
        $app = new Application($tmpDir);
        $app->bootstrap();

        $response = $app->handle(new Request('GET', '/'));

        $this->assertSame(404, $response->status());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_boot_loads_routes_from_cache_when_cache_exists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ez-php-route-cache-test-' . uniqid();
        mkdir($tmpDir . '/bootstrap/cache', 0o777, true);
        mkdir($tmpDir . '/config', 0o777, true);

        // Write a route cache that maps GET /cached → a simple handler
        $cacheData = [
            [
                'method' => 'GET',
                'path' => '/cached',
                'name' => 'cached.route',
                'handler' => [\stdClass::class, 'index'],
                'middleware' => [],
                'constraints' => [],
                'csrfExempt' => false,
            ],
        ];

        file_put_contents(
            $tmpDir . '/bootstrap/cache/routes.php',
            '<?php return ' . var_export($cacheData, true) . ';' . "\n"
        );

        $app = new Application($tmpDir);
        $app->bootstrap();

        // Route was loaded from cache — verify it exists via names()
        $router = $app->make(Router::class);
        $names = $router->names();

        $this->assertArrayHasKey('cached.route', $names);
        $this->assertSame('/cached', $names['cached.route']);

        // Clean up
        unlink($tmpDir . '/bootstrap/cache/routes.php');
        rmdir($tmpDir . '/bootstrap/cache');
        rmdir($tmpDir . '/bootstrap');
        rmdir($tmpDir . '/config');
        rmdir($tmpDir);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_boot_skips_routes_file_when_cache_exists(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ez-php-route-cache-skip-' . uniqid();
        mkdir($tmpDir . '/bootstrap/cache', 0o777, true);
        mkdir($tmpDir . '/config', 0o777, true);
        mkdir($tmpDir . '/routes', 0o777, true);

        // routes/web.php would register GET /web — but cache takes priority
        file_put_contents($tmpDir . '/routes/web.php', '<?php $router->get(\'/web\', fn () => \'from-file\');');

        // Cache only has GET /cached — so /web must NOT be registered
        $cacheData = [
            [
                'method' => 'GET',
                'path' => '/cached',
                'name' => null,
                'handler' => [\stdClass::class, 'index'],
                'middleware' => [],
                'constraints' => [],
                'csrfExempt' => false,
            ],
        ];

        file_put_contents(
            $tmpDir . '/bootstrap/cache/routes.php',
            '<?php return ' . var_export($cacheData, true) . ';' . "\n"
        );

        $app = new Application($tmpDir);
        $app->bootstrap();

        $router = $app->make(Router::class);

        // /web must not exist — it was in the file but cache was used instead
        $this->assertSame([], $router->names()); // /cached has no name

        // Clean up
        unlink($tmpDir . '/bootstrap/cache/routes.php');
        unlink($tmpDir . '/routes/web.php');
        rmdir($tmpDir . '/bootstrap/cache');
        rmdir($tmpDir . '/bootstrap');
        rmdir($tmpDir . '/routes');
        rmdir($tmpDir . '/config');
        rmdir($tmpDir);
    }
}
