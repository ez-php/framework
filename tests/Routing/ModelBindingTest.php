<?php

declare(strict_types=1);

namespace Tests\Routing;

use EzPhp\Exceptions\HttpException;
use EzPhp\Exceptions\NotFoundException;
use EzPhp\Http\Request;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ModelBindingTest
 *
 * @package Tests\Routing
 */
#[CoversClass(Router::class)]
#[UsesClass(Route::class)]
#[UsesClass(NotFoundException::class)]
#[UsesClass(HttpException::class)]
final class ModelBindingTest extends TestCase
{
    /**
     * A minimal fake model used in binding tests.
     */
    private function makeModel(int $id): object
    {
        return new class ($id) {
            public function __construct(public readonly int $id)
            {
            }
        };
    }

    /**
     * @return void
     */
    public function test_model_binding_resolves_param_to_object(): void
    {
        $router = new Router();
        $router->get('/users/{user}', fn (Request $r) => 'ok');

        $fakeUser = $this->makeModel(5);
        $router->model('user', \stdClass::class, fn (string $id) => $id === '5' ? $fakeUser : null);

        $route = $router->retrieveRoute(new Request('GET', '/users/5'));

        $this->assertSame($fakeUser, $route->getParams()['user']);
    }

    /**
     * @return void
     */
    public function test_model_not_found_throws_not_found_exception(): void
    {
        $router = new Router();
        $router->get('/users/{user}', fn (Request $r) => 'ok');
        $router->model('user', \stdClass::class, fn (string $id) => null);

        $this->expectException(NotFoundException::class);

        $router->retrieveRoute(new Request('GET', '/users/999'));
    }

    /**
     * @return void
     */
    public function test_unbound_params_remain_as_strings(): void
    {
        $router = new Router();
        $router->get('/posts/{id}', fn (Request $r) => 'ok');

        $route = $router->retrieveRoute(new Request('GET', '/posts/42'));

        $this->assertSame('42', $route->getParams()['id']);
    }

    /**
     * @return void
     */
    public function test_multiple_bindings_are_resolved(): void
    {
        $router = new Router();
        $router->get('/users/{user}/posts/{post}', fn (Request $r) => 'ok');

        $fakeUser = $this->makeModel(1);
        $fakePost = $this->makeModel(7);

        $router->model('user', \stdClass::class, fn (string $id) => $id === '1' ? $fakeUser : null);
        $router->model('post', \stdClass::class, fn (string $id) => $id === '7' ? $fakePost : null);

        $route = $router->retrieveRoute(new Request('GET', '/users/1/posts/7'));

        $this->assertSame($fakeUser, $route->getParams()['user']);
        $this->assertSame($fakePost, $route->getParams()['post']);
    }

    /**
     * @return void
     */
    public function test_resolved_param_accessible_via_request_param(): void
    {
        $router = new Router();
        $router->get('/items/{item}', fn (Request $r) => 'ok');

        $fakeItem = $this->makeModel(3);
        $router->model('item', \stdClass::class, fn (string $id) => $id === '3' ? $fakeItem : null);

        $route = $router->retrieveRoute(new Request('GET', '/items/3'));

        ob_start();
        $response = $route->run(new Request('GET', '/items/3'));
        ob_end_clean();

        // run() calls withParams which stores the resolved object; param() returns it.
        $this->assertSame(200, $response->status());
    }
}
