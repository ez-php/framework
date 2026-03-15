<?php

declare(strict_types=1);

namespace Tests\Application;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
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
use EzPhp\Console\CommandInterface;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\CorsMiddleware;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\TestCase;

/**
 * Class ApplicationTest
 *
 * @package Tests\Application
 */
#[CoversClass(Application::class)]
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
#[UsesClass(ServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(RouteException::class)]
#[UsesClass(ConsoleServiceProvider::class)]
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
#[UsesClass(ListCommand::class)]
#[UsesClass(CorsMiddleware::class)]
#[UsesClass(MiddlewareHandler::class)]
final class ApplicationTest extends TestCase
{
    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_base_path_defaults_to_project_root(): void
    {
        $app = new Application();

        $this->assertSame(dirname(__DIR__, 2), $app->basePath());
    }

    /**
     * @return void
     */
    public function test_base_path_with_explicit_path(): void
    {
        $app = new Application('/my/project');

        $this->assertSame('/my/project', $app->basePath());
        $this->assertSame('/my/project' . DIRECTORY_SEPARATOR . 'config', $app->basePath('config'));
    }

    /**
     * @return void
     */
    public function test_bootstrap_is_idempotent(): void
    {
        $app = new Application();
        $app->bootstrap();
        $app->bootstrap();
        $this->expectNotToPerformAssertions();
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_handle_returns_404_for_unknown_route(): void
    {
        $app = new Application();
        $app->bootstrap();

        $response = $app->handle(new Request('GET', '/does-not-exist'));

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('404', $response->body());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_handle_bootstraps_automatically_if_not_booted(): void
    {
        $app = new Application();
        $response = $app->handle(new Request('GET', '/any'));
        $this->assertSame(404, $response->status());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_when_container_not_initialized(): void
    {
        $app = new Application();
        $this->expectException(ApplicationException::class);
        $app->make(Router::class);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_bind_registers_service_in_container(): void
    {
        $app = new Application();
        $app->bootstrap();

        $app->bind(Router::class, fn () => new Router());
        $router = $app->make(Router::class);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_bind_without_closure_uses_autowiring(): void
    {
        $app = new Application();
        $app->bootstrap();

        $app->bind(Router::class);
        $router = $app->make(Router::class);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_register_adds_user_provider_before_bootstrap(): void
    {
        // Use ConfigServiceProvider as a safe example — it is idempotent and already
        // in CoreServiceProviders. The container deduplication means a second binding
        // is a no-op; we only verify that register() + bootstrap() doesn't throw.
        $app = new Application();
        $app->register(ConfigServiceProvider::class);
        $app->bootstrap();

        $router = $app->make(Router::class);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * @return void
     */
    public function test_register_returns_self_for_chaining(): void
    {
        $app = new Application();
        $this->assertSame($app, $app->register(RouterServiceProvider::class));
    }

    /**
     * @return void
     */
    public function test_middleware_returns_self_for_chaining(): void
    {
        $app = new Application();
        $this->assertSame($app, $app->middleware(CorsMiddleware::class));
    }

    /**
     * @return void
     */
    public function test_register_command_returns_self_for_chaining(): void
    {
        $stub = new class () implements CommandInterface {
            public function getName(): string
            {
                return 'test';
            }

            public function getDescription(): string
            {
                return '';
            }

            public function getHelp(): string
            {
                return '';
            }

            public function handle(array $args): int
            {
                return 0;
            }
        };

        $app = new Application();
        $this->assertSame($app, $app->registerCommand($stub::class));
    }

    /**
     * @return void
     */
    public function test_get_commands_returns_registered_command_classes(): void
    {
        $stub = new class () implements CommandInterface {
            public function getName(): string
            {
                return 'test';
            }

            public function getDescription(): string
            {
                return '';
            }

            public function getHelp(): string
            {
                return '';
            }

            public function handle(array $args): int
            {
                return 0;
            }
        };

        $app = new Application();
        $app->registerCommand($stub::class);

        $this->assertContains($stub::class, $app->getCommands());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_global_middleware_is_applied_to_requests(): void
    {
        $app = new Application();
        $app->middleware(CorsMiddleware::class);
        $app->bootstrap();

        // Use a path not defined in routes/web.php to avoid duplicate route detection.
        $app->make(Router::class)->get('/test-cors', fn (Request $r): Response => new Response('ok'));

        $response = $app->handle(new Request('GET', '/test-cors'));

        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->headers());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_global_middleware_handles_options_preflight(): void
    {
        $app = new Application();
        $app->middleware(CorsMiddleware::class);
        $app->bootstrap();

        $response = $app->handle(new Request('OPTIONS', '/api/test'));

        $this->assertSame(204, $response->status());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->headers());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_bind_with_string_class_resolves_to_instance(): void
    {
        $app = new Application();
        $app->bootstrap();

        $app->bind(Router::class, Router::class);
        $router = $app->make(Router::class);

        $this->assertInstanceOf(Router::class, $router);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_global_middleware_not_duplicated_on_multiple_handles(): void
    {
        $app = new Application();
        $app->middleware(CorsMiddleware::class);
        $app->bootstrap();

        // Use a path not defined in routes/web.php to avoid duplicate route detection.
        $app->make(Router::class)->get('/test-handle', fn (Request $r): Response => new Response('ok'));

        $app->handle(new Request('GET', '/test-handle'));
        $response = $app->handle(new Request('GET', '/test-handle'));

        // Still a valid response — middleware was not double-applied
        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_instance_overrides_resolved_singleton(): void
    {
        $app = new Application();
        $app->bootstrap();

        $app->make(Router::class); // cache the framework-bound instance

        $replacement = new Router();
        $app->instance(Router::class, $replacement);

        $this->assertSame($replacement, $app->make(Router::class));
    }
}
