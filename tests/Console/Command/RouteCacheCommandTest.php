<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Application\Application;
use EzPhp\Console\Command\RouteCacheCommand;
use EzPhp\Console\Command\RouteClearCommand;
use EzPhp\Middleware\CorsMiddleware;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class RouteCacheCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(RouteCacheCommand::class)]
#[CoversClass(RouteClearCommand::class)]
#[UsesClass(Router::class)]
#[UsesClass(Route::class)]
#[UsesClass(Application::class)]
#[UsesClass(CorsMiddleware::class)]
final class RouteCacheCommandTest extends TestCase
{
    private string $cachePath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/ez-php-route-cache-' . uniqid() . '.php';
    }

    /**
     * Create a Router with a ContainerInterface so array-handler routes can be registered.
     * Application is used because it implements ContainerInterface.
     */
    private function routerWithContainer(): Router
    {
        return new Router(new Application());
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    // ── RouteCacheCommand ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_name_and_description(): void
    {
        $router = new Router();
        $command = new RouteCacheCommand($router, $this->cachePath);

        $this->assertSame('route:cache', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_cache_writes_file_for_array_handler_routes(): void
    {
        $router = $this->routerWithContainer();
        $router->add('GET', '/users', [\stdClass::class, 'index'])->name('users.index');

        $command = new RouteCacheCommand($router, $this->cachePath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->cachePath);

        /** @var list<array<string, mixed>> $data */
        $data = require $this->cachePath;

        $this->assertCount(1, $data);
        $this->assertSame('GET', $data[0]['method']);
        $this->assertSame('/users', $data[0]['path']);
        $this->assertSame('users.index', $data[0]['name']);
        $this->assertSame([\stdClass::class, 'index'], $data[0]['handler']);
    }

    /**
     * @return void
     */
    public function test_cache_skips_closure_routes(): void
    {
        $router = $this->routerWithContainer();
        $router->get('/closure', fn () => 'hello');
        $router->add('GET', '/controller', [\stdClass::class, 'show']);

        $command = new RouteCacheCommand($router, $this->cachePath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);

        /** @var list<array<string, mixed>> $data */
        $data = require $this->cachePath;

        $this->assertCount(1, $data);
        $this->assertSame('/controller', $data[0]['path']);
    }

    /**
     * @return void
     */
    public function test_cache_preserves_middleware_and_constraints(): void
    {
        $router = $this->routerWithContainer();
        $router->add('GET', '/users/{id}', [\stdClass::class, 'show'])
            ->where('id', '[0-9]+')
            ->middleware(CorsMiddleware::class)
            ->withoutCsrf();

        $command = new RouteCacheCommand($router, $this->cachePath);

        ob_start();
        $command->handle([]);
        ob_get_clean();

        /** @var list<array<string, mixed>> $data */
        $data = require $this->cachePath;

        $this->assertSame(['id' => '[0-9]+'], $data[0]['constraints']);
        $this->assertSame([CorsMiddleware::class], $data[0]['middleware']);
        $this->assertTrue($data[0]['csrfExempt']);
    }

    /**
     * @return void
     */
    public function test_cache_prints_warning_when_no_cacheable_routes(): void
    {
        $router = new Router();
        $router->get('/only-closure', fn () => 'hello');

        $command = new RouteCacheCommand($router, $this->cachePath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($this->cachePath);
    }

    /**
     * @return void
     */
    public function test_cache_creates_directory_if_absent(): void
    {
        $nestedPath = sys_get_temp_dir() . '/ez-php-route-cache-nested-' . uniqid() . '/routes.php';

        $router = $this->routerWithContainer();
        $router->add('GET', '/users', [\stdClass::class, 'index']);

        $command = new RouteCacheCommand($router, $nestedPath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($nestedPath);

        unlink($nestedPath);
        rmdir(dirname($nestedPath));
    }

    // ── RouteClearCommand ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_clear_name_and_description(): void
    {
        $command = new RouteClearCommand($this->cachePath);

        $this->assertSame('route:clear', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_clear_removes_existing_cache_file(): void
    {
        file_put_contents($this->cachePath, '<?php return [];');

        $command = new RouteClearCommand($this->cachePath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($this->cachePath);
    }

    /**
     * @return void
     */
    public function test_clear_returns_zero_when_no_cache_exists(): void
    {
        $command = new RouteClearCommand($this->cachePath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(0, $code);
    }
}
