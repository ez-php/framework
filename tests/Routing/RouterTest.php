<?php

declare(strict_types=1);

namespace Tests\Routing;

use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareInterface;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class RouterTest
 *
 * @package Tests\Routing
 */
#[CoversClass(Router::class)]
#[CoversClass(Route::class)]
#[CoversClass(RouteException::class)]
final class RouterTest extends TestCase
{
    /**
     * @return void
     * @throws RouteException
     */
    public function test_get_registers_get_route(): void
    {
        $router = new Router();
        $router->get('/hello', fn () => 'hello');

        $request = new Request('GET', '/hello');
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_post_registers_post_route(): void
    {
        $router = new Router();
        $router->post('/submit', fn () => 'ok');

        $request = new Request('POST', '/submit');
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_add_registers_custom_method_route(): void
    {
        $router = new Router();
        $router->add('DELETE', '/item', fn () => 'deleted');

        $request = new Request('DELETE', '/item');
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_retrieve_route_throws_for_unknown_path(): void
    {
        $router = new Router();
        $this->expectException(RouteException::class);
        $this->expectExceptionMessage('Route not found');
        $router->retrieveRoute(new Request('GET', '/missing'));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_retrieve_route_throws_for_wrong_method(): void
    {
        $router = new Router();
        $router->get('/hello', fn () => 'hello');
        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('POST', '/hello'));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_route_run_returns_response_from_string(): void
    {
        $router = new Router();
        $router->get('/hi', fn () => 'Hi!');

        $request = new Request('GET', '/hi');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('Hi!', $response->body());
        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_route_middleware_registers_and_is_retrievable(): void
    {
        $router = new Router();
        $route = $router->get('/protected', fn () => 'ok');

        /** @var class-string<MiddlewareInterface> $mw */
        $mw = MiddlewareInterface::class;
        $route->middleware($mw);

        $this->assertSame([$mw], $route->getMiddleware());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_route_run_returns_response_directly_when_handler_returns_response(): void
    {
        $router = new Router();

        $expectedResponse = new Response('custom', 201);
        $router->get('/custom', fn () => $expectedResponse);

        $request = new Request('GET', '/custom');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_dynamic_route_matches_with_single_param(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn (Request $r) => $r->param('id'));

        $request = new Request('GET', '/users/42');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('42', $response->body());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_dynamic_route_matches_with_multiple_params(): void
    {
        $router = new Router();
        $router->get('/users/{userId}/posts/{postId}', function (Request $r): string {
            /** @var string $userId */
            $userId = $r->param('userId');
            /** @var string $postId */
            $postId = $r->param('postId');
            return $userId . ':' . $postId;
        });

        $request = new Request('GET', '/users/5/posts/99');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('5:99', $response->body());
    }

    /**
     * @return void
     */
    public function test_dynamic_route_does_not_match_wrong_segment_count(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn () => 'ok');

        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('GET', '/users/42/extra'));
    }

    // --- Trailing-Slash-Normalisierung ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_trailing_slash_on_uri_matches_route_without_slash(): void
    {
        $router = new Router();
        $router->get('/users', fn () => 'users');

        $route = $router->retrieveRoute(new Request('GET', '/users/'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_trailing_slash_on_registered_route_matches_uri_without_slash(): void
    {
        $router = new Router();
        $router->get('/users/', fn () => 'users');

        $route = $router->retrieveRoute(new Request('GET', '/users'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_root_slash_is_not_stripped(): void
    {
        $router = new Router();
        $router->get('/', fn () => 'root');

        $route = $router->retrieveRoute(new Request('GET', '/'));
        $this->assertInstanceOf(Route::class, $route);
    }

    // --- Optionale Route-Segmente ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_optional_param_matches_when_provided(): void
    {
        $router = new Router();
        $router->get('/users/{id?}', fn (Request $r) => $r->param('id') ?? 'none');

        $request = new Request('GET', '/users/42');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('42', $response->body());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_optional_param_matches_when_absent(): void
    {
        $router = new Router();
        $router->get('/users/{id?}', fn (Request $r) => $r->param('id') ?? 'none');

        $request = new Request('GET', '/users');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('none', $response->body());
    }

    // --- Route-Gruppen ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_group_prepends_prefix_to_routes(): void
    {
        $router = new Router();
        $router->group('/api', function (Router $r): void {
            $r->get('/users', fn () => 'users');
            $r->get('/posts', fn () => 'posts');
        });

        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/api/users')));
        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/api/posts')));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_group_prefix_does_not_affect_routes_outside_group(): void
    {
        $router = new Router();
        $router->get('/home', fn () => 'home');
        $router->group('/api', function (Router $r): void {
            $r->get('/users', fn () => 'api-users');
        });

        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/home')));
        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/api/users')));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_nested_groups_accumulate_prefixes(): void
    {
        $router = new Router();
        $router->group('/api', function (Router $r): void {
            $r->group('/v1', function (Router $r): void {
                $r->get('/users', fn () => 'v1-users');
            });
        });

        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/api/v1/users')));
    }

    // --- Named Routes ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_named_route_generates_url_without_params(): void
    {
        $router = new Router();
        $router->get('/users', fn () => 'users')->name('users.index');

        $this->assertSame('/users', $router->route('users.index'));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_named_route_generates_url_with_params(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn () => 'user')->name('users.show');

        $this->assertSame('/users/42', $router->route('users.show', ['id' => '42']));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_named_route_generates_url_with_optional_param_provided(): void
    {
        $router = new Router();
        $router->get('/users/{id?}', fn () => 'user')->name('users.show');

        $this->assertSame('/users/42', $router->route('users.show', ['id' => '42']));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_named_route_generates_url_with_optional_param_absent(): void
    {
        $router = new Router();
        $router->get('/users/{id?}', fn () => 'users')->name('users.index');

        $this->assertSame('/users', $router->route('users.index'));
    }

    /**
     * @return void
     */
    public function test_named_route_throws_for_unknown_name(): void
    {
        $router = new Router();
        $this->expectException(RouteException::class);
        $router->route('does.not.exist');
    }

    /**
     * @return void
     */
    public function test_route_name_is_retrievable(): void
    {
        $router = new Router();
        $route = $router->get('/users', fn () => 'users')->name('users.index');

        $this->assertSame('users.index', $route->getName());
    }

    // --- HTTP Method Override ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_method_override_put_via_post_form(): void
    {
        $router = new Router();
        $router->put('/users/1', fn () => 'updated');

        $request = new Request('POST', '/users/1', body: ['_method' => 'PUT']);
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_method_override_delete_via_post_form(): void
    {
        $router = new Router();
        $router->delete('/users/1', fn () => 'deleted');

        $request = new Request('POST', '/users/1', body: ['_method' => 'DELETE']);
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_method_override_is_case_insensitive(): void
    {
        $router = new Router();
        $router->put('/items/1', fn () => 'ok');

        $request = new Request('POST', '/items/1', body: ['_method' => 'put']);
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_method_override_ignored_for_non_post_requests(): void
    {
        $router = new Router();
        $router->get('/users/1', fn () => 'ok');

        // GET request with _method in query should NOT override
        $request = new Request('GET', '/users/1', query: ['_method' => 'DELETE']);
        $route = $router->retrieveRoute($request);
        $this->assertInstanceOf(Route::class, $route);
    }

    // --- Group-Middleware ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_group_applies_middleware_to_routes_inside_group(): void
    {
        /** @var class-string<MiddlewareInterface> $mw */
        $mw = MiddlewareInterface::class;

        $router = new Router();
        $router->group('/api', function (Router $r): void {
            $r->get('/users', fn () => 'users');
        }, [$mw]);

        $route = $router->retrieveRoute(new Request('GET', '/api/users'));
        $this->assertSame([$mw], $route->getMiddleware());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_group_middleware_does_not_apply_outside_group(): void
    {
        /** @var class-string<MiddlewareInterface> $mw */
        $mw = MiddlewareInterface::class;

        $router = new Router();
        $router->get('/home', fn () => 'home');
        $router->group('/api', function (Router $r): void {
            $r->get('/users', fn () => 'users');
        }, [$mw]);

        $route = $router->retrieveRoute(new Request('GET', '/home'));
        $this->assertSame([], $route->getMiddleware());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_nested_group_middleware_accumulates(): void
    {
        /** @var class-string<MiddlewareInterface> $mw1 */
        $mw1 = MiddlewareInterface::class;

        $router = new Router();
        $router->group('/api', function (Router $r) use ($mw1): void {
            $r->group('/v1', function (Router $r): void {
                $r->get('/users', fn () => 'users');
            }, [$mw1]);
        }, [$mw1]);

        $route = $router->retrieveRoute(new Request('GET', '/api/v1/users'));
        // Both group layers contribute mw1 — the route ends up with it twice
        $this->assertCount(2, $route->getMiddleware());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_group_middleware_restores_after_group(): void
    {
        /** @var class-string<MiddlewareInterface> $mw */
        $mw = MiddlewareInterface::class;

        $router = new Router();
        $router->group('/api', function (Router $r): void {
            $r->get('/users', fn () => 'users');
        }, [$mw]);
        $router->get('/public', fn () => 'public');

        $publicRoute = $router->retrieveRoute(new Request('GET', '/public'));
        $this->assertSame([], $publicRoute->getMiddleware());
    }

    // --- Redirect-Routes ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_redirect_registers_get_route_returning_redirect_response(): void
    {
        $router = new Router();
        $router->redirect('/old', '/new');

        $request = new Request('GET', '/old');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());
        $this->assertSame('/new', $response->headers()['Location']);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_redirect_supports_custom_status_code(): void
    {
        $router = new Router();
        $router->redirect('/legacy', '/current', 301);

        $request = new Request('GET', '/legacy');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame(301, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());
        $this->assertSame('/current', $response->headers()['Location']);
    }

    // --- put() / delete() Hilfsmethoden ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_put_registers_put_route(): void
    {
        $router = new Router();
        $router->put('/users/1', fn () => 'updated');

        $route = $router->retrieveRoute(new Request('PUT', '/users/1'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_delete_registers_delete_route(): void
    {
        $router = new Router();
        $router->delete('/users/1', fn () => 'deleted');

        $route = $router->retrieveRoute(new Request('DELETE', '/users/1'));
        $this->assertInstanceOf(Route::class, $route);
    }
}
