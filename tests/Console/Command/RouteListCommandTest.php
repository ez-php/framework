<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\RouteListCommand;
use EzPhp\Middleware\CorsMiddleware;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class RouteListCommandTest
 *
 * @package Tests\Console\Command
 */
#[CoversClass(RouteListCommand::class)]
#[UsesClass(Router::class)]
#[UsesClass(Route::class)]
final class RouteListCommandTest extends TestCase
{
    /**
     * @return void
     */
    public function test_name_and_description(): void
    {
        $command = new RouteListCommand(new Router());

        $this->assertSame('route:list', $command->getName());
        $this->assertNotEmpty($command->getDescription());
        $this->assertNotEmpty($command->getHelp());
    }

    /**
     * @return void
     */
    public function test_handle_prints_registered_routes(): void
    {
        $router = new Router();
        $router->get('/users', fn () => 'users')->name('users.index');
        $router->post('/users', fn () => 'create')->middleware(CorsMiddleware::class);

        $command = new RouteListCommand($router);

        ob_start();
        $code = $command->handle([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('GET', (string) $output);
        $this->assertStringContainsString('/users', (string) $output);
        $this->assertStringContainsString('users.index', (string) $output);
        $this->assertStringContainsString('POST', (string) $output);
        $this->assertStringContainsString(CorsMiddleware::class, (string) $output);
    }

    /**
     * @return void
     */
    public function test_handle_prints_message_when_no_routes_registered(): void
    {
        $command = new RouteListCommand(new Router());

        ob_start();
        $code = $command->handle([]);
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('No routes registered', (string) $output);
    }
}
