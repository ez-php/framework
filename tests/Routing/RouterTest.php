<?php

declare(strict_types=1);

namespace Tests\Routing;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Exceptions\NotFoundException;
use EzPhp\Exceptions\RouteException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareInterface;
use EzPhp\Routing\ResourceControllerInterface;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class RouterTest
 *
 * @package Tests\Routing
 */
#[CoversClass(Router::class)]
#[CoversClass(Route::class)]
#[CoversClass(RouteException::class)]
#[UsesClass(NotFoundException::class)]
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
    /**
     * @return void
     * @throws RouteException
     */
    public function test_named_route_generates_url_strips_literal_preceding_unprovided_optional(): void
    {
        $router = new Router();
        $router->get('/users/{id?}/posts/{postId?}', fn () => 'ok')->name('user.posts');

        // Both provided — full URL
        $this->assertSame('/users/123/posts/456', $router->route('user.posts', ['id' => '123', 'postId' => '456']));
        // Only id provided — /posts literal must be removed because postId is absent
        $this->assertSame('/users/123', $router->route('user.posts', ['id' => '123']));
        // Neither provided — base literal stays
        $this->assertSame('/users', $router->route('user.posts'));
    }

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

    // --- Group state restored after exception ---

    /**
     * @return void
     */
    public function test_group_prefix_restored_after_callback_throws(): void
    {
        $router = new Router();

        try {
            $router->group('/api', function (Router $r): void {
                $r->get('/users', fn () => 'users');
                throw new \RuntimeException('callback error');
            });
        } catch (\RuntimeException) {
            // expected
        }

        // Route registered after the failed group must not inherit /api prefix
        $router->get('/health', fn () => 'ok');
        $route = $router->retrieveRoute(new Request('GET', '/health'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     */
    public function test_group_middleware_restored_after_callback_throws(): void
    {
        /** @var class-string<MiddlewareInterface> $mw */
        $mw = MiddlewareInterface::class;

        $router = new Router();

        try {
            $router->group('/api', function (Router $r): void {
                throw new \RuntimeException('callback error');
            }, [$mw]);
        } catch (\RuntimeException) {
            // expected
        }

        // Route registered after the failed group must not inherit group middleware
        $router->get('/public', fn () => 'public');
        $public = $router->retrieveRoute(new Request('GET', '/public'));
        $this->assertSame([], $public->getMiddleware());
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

    // --- Resource Routes ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_index_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('GET', '/posts'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_create_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('GET', '/posts/create'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_store_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('POST', '/posts'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_show_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('GET', '/posts/42'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_edit_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('GET', '/posts/42/edit'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_update_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('PUT', '/posts/42'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_registers_destroy_route(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $route = $router->retrieveRoute(new Request('DELETE', '/posts/42'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_named_routes_are_generated(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $this->assertSame('/posts', $router->route('posts.index'));
        $this->assertSame('/posts/create', $router->route('posts.create'));
        $this->assertSame('/posts', $router->route('posts.store'));
        $this->assertSame('/posts/42', $router->route('posts.show', ['id' => '42']));
        $this->assertSame('/posts/42/edit', $router->route('posts.edit', ['id' => '42']));
        $this->assertSame('/posts/42', $router->route('posts.update', ['id' => '42']));
        $this->assertSame('/posts/42', $router->route('posts.destroy', ['id' => '42']));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_dispatches_index_action(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $request = new Request('GET', '/posts');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('index', $response->body());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_dispatches_show_with_id_param(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController());

        $request = new Request('GET', '/posts/99');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('show:99', $response->body());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_works_inside_group(): void
    {
        $router = new Router();
        $router->group('/api', function (Router $r): void {
            $r->resource('posts', new ResourceTestController());
        });

        $route = $router->retrieveRoute(new Request('GET', '/api/posts'));
        $this->assertInstanceOf(Route::class, $route);
        $route = $router->retrieveRoute(new Request('GET', '/api/posts/5'));
        $this->assertInstanceOf(Route::class, $route);
    }

    // --- Duplicate Routes ---

    /**
     * @return void
     */
    public function test_add_throws_for_duplicate_route(): void
    {
        $router = new Router();
        $router->get('/users', fn () => 'users');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate route: GET /users is already registered.');
        $router->get('/users', fn () => 'users');
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

    // --- patch() ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_patch_registers_patch_route(): void
    {
        $router = new Router();
        $router->patch('/users/1', fn () => 'patched');

        $route = $router->retrieveRoute(new Request('PATCH', '/users/1'));
        $this->assertInstanceOf(Route::class, $route);
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_patch_route_runs_and_returns_response(): void
    {
        $router = new Router();
        $router->patch('/users/1', fn () => 'patched');

        $request = new Request('PATCH', '/users/1');
        $route = $router->retrieveRoute($request);
        $this->assertSame('patched', $route->run($request)->body());
    }

    // --- Partial resource routes: only ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_only_registers_specified_actions(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController(), only: ['index', 'show']);

        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/posts')));
        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/posts/1')));
    }

    /**
     * @return void
     */
    public function test_resource_only_excludes_unspecified_actions(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController(), only: ['index']);

        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('POST', '/posts'));
    }

    // --- Partial resource routes: except ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_resource_except_excludes_specified_actions(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController(), except: ['create', 'edit']);

        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/posts')));
        $this->assertInstanceOf(Route::class, $router->retrieveRoute(new Request('GET', '/posts/1')));
    }

    /**
     * @return void
     */
    public function test_resource_except_removes_excluded_routes(): void
    {
        $router = new Router();
        $router->resource('posts', new ResourceTestController(), except: ['destroy']);

        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('DELETE', '/posts/1'));
    }

    // --- Array dispatch: [Controller::class, 'method'] ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_array_handler_is_resolved_from_container_at_dispatch(): void
    {
        $container = new ArrayDispatchTestContainer();
        $router = new Router($container);
        $router->get('/items', [ArrayDispatchTestController::class, 'index']);

        $request = new Request('GET', '/items');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('items-index', $response->body());
    }

    /**
     * @return void
     */
    public function test_array_handler_without_container_throws(): void
    {
        $router = new Router();

        $this->expectException(InvalidArgumentException::class);
        $router->get('/items', [ArrayDispatchTestController::class, 'index']);
    }

    // --- Regex Constraints (item 28) ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_where_constraint_matches_valid_segment(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn (Request $r) => $r->param('id'))->where('id', '[0-9]+');

        $request = new Request('GET', '/users/42');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('42', $response->body());
    }

    /**
     * @return void
     */
    public function test_where_constraint_rejects_non_matching_segment(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn () => 'ok')->where('id', '[0-9]+');

        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('GET', '/users/abc'));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_where_constraint_on_optional_param_matches_when_provided(): void
    {
        $router = new Router();
        $router->get('/posts/{slug?}', fn (Request $r) => $r->param('slug') ?? 'none')
            ->where('slug', '[a-z\-]+');

        $request = new Request('GET', '/posts/my-post');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('my-post', $response->body());
    }

    /**
     * @return void
     */
    public function test_where_constraint_on_optional_param_rejects_invalid_value(): void
    {
        $router = new Router();
        $router->get('/posts/{slug?}', fn () => 'ok')->where('slug', '[a-z\-]+');

        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('GET', '/posts/123'));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_where_constraint_falls_back_when_no_constraint_set(): void
    {
        $router = new Router();
        $router->get('/items/{id}', fn (Request $r) => $r->param('id'));

        $request = new Request('GET', '/items/any-value');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('any-value', $response->body());
    }

    // --- where() rejects invalid regex patterns ---

    /**
     * @return void
     */
    public function test_where_throws_on_invalid_regex_pattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/id/');

        $router = new Router();
        $router->get('/users/{id}', fn () => 'ok')->where('id', '[');
    }

    /**
     * @return void
     */
    public function test_where_throws_on_invalid_regex_pattern_for_optional_param(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $router = new Router();
        $router->get('/posts/{slug?}', fn () => 'ok')->where('slug', '(unclosed');
    }

    // --- Fallback Route (item 29) ---

    /**
     * @return void
     * @throws RouteException
     */
    public function test_fallback_matches_unmatched_get_request(): void
    {
        $router = new Router();
        $router->get('/home', fn () => 'home');
        $router->fallback(fn () => 'fallback');

        $request = new Request('GET', '/missing');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('fallback', $response->body());
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_fallback_matches_any_http_method(): void
    {
        $router = new Router();
        $router->fallback(fn () => 'fallback');

        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $request = new Request($method, '/missing');
            $route = $router->retrieveRoute($request);
            $this->assertSame('fallback', $route->run($request)->body());
        }
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_fallback_is_not_used_when_route_matches(): void
    {
        $router = new Router();
        $router->get('/home', fn () => 'home');
        $router->fallback(fn () => 'fallback');

        $request = new Request('GET', '/home');
        $route = $router->retrieveRoute($request);
        $response = $route->run($request);

        $this->assertSame('home', $response->body());
    }

    /**
     * @return void
     */
    public function test_without_fallback_throws_route_exception(): void
    {
        $router = new Router();
        $router->get('/home', fn () => 'home');

        $this->expectException(RouteException::class);
        $router->retrieveRoute(new Request('GET', '/missing'));
    }

    /**
     * @return void
     * @throws RouteException
     */
    public function test_fallback_does_not_participate_in_duplicate_detection(): void
    {
        $router = new Router();
        $router->fallback(fn () => 'first');
        $router->fallback(fn () => 'second'); // Should not throw

        $route = $router->retrieveRoute(new Request('GET', '/any'));
        $this->assertSame('second', $route->run(new Request('GET', '/any'))->body());
    }

    // ── model bindings ────────────────────────────────────────────────────────

    /**
     * Default resolver calls <ModelClass>::find($id) and returns the bound object.
     *
     * @return void
     * @throws RouteException
     */
    public function test_model_default_resolver_returns_found_model(): void
    {
        $router = new Router();
        $router->get('/users/{user}', fn (Request $r): string => 'ok');
        $router->model('user', ModelBindingStub::class);

        $request = new Request('GET', '/users/42');
        $route = $router->retrieveRoute($request);

        $this->assertInstanceOf(ModelBindingStub::class, $route->getParams()['user']);
        /** @var ModelBindingStub $model */
        $model = $route->getParams()['user'];
        $this->assertSame('42', $model->id);
    }

    /**
     * A custom resolver closure is used instead of the default find().
     *
     * @return void
     * @throws RouteException
     */
    public function test_model_custom_resolver_is_used(): void
    {
        $router = new Router();
        $router->get('/posts/{post}', fn (Request $r): string => 'ok');
        $router->model('post', ModelBindingStub::class, fn (string $id): object => new ModelBindingStub('custom-' . $id));

        $request = new Request('GET', '/posts/7');
        $route = $router->retrieveRoute($request);

        /** @var ModelBindingStub $model */
        $model = $route->getParams()['post'];
        $this->assertSame('custom-7', $model->id);
    }

    /**
     * Resolver returning null throws NotFoundException.
     *
     * @return void
     */
    public function test_model_resolver_returning_null_throws_not_found(): void
    {
        $router = new Router();
        $router->get('/items/{item}', fn (Request $r): string => 'ok');
        $router->model('item', ModelBindingStub::class, fn (string $id): ?object => null);

        $this->expectException(NotFoundException::class);
        $router->retrieveRoute(new Request('GET', '/items/99'));
    }

    /**
     * Default resolver returns null (model not found) → NotFoundException.
     *
     * @return void
     */
    public function test_model_default_resolver_null_throws_not_found(): void
    {
        $router = new Router();
        $router->get('/things/{thing}', fn (Request $r): string => 'ok');
        $router->model('thing', ModelBindingNotFoundStub::class);

        $this->expectException(NotFoundException::class);
        $router->retrieveRoute(new Request('GET', '/things/1'));
    }
}

/**
 * Class ResourceTestController
 *
 * @package Tests\Routing
 */
class ResourceTestController implements ResourceControllerInterface
{
    /**
     * @param Request $request
     *
     * @return string
     */
    public function index(Request $request): string
    {
        return 'index';
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function create(Request $request): string
    {
        return 'create';
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function store(Request $request): string
    {
        return 'store';
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function show(Request $request): string
    {
        $id = $request->param('id');
        return 'show:' . (is_string($id) ? $id : '');
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function edit(Request $request): string
    {
        $id = $request->param('id');
        return 'edit:' . (is_string($id) ? $id : '');
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function update(Request $request): string
    {
        $id = $request->param('id');
        return 'update:' . (is_string($id) ? $id : '');
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function destroy(Request $request): string
    {
        $id = $request->param('id');
        return 'destroy:' . (is_string($id) ? $id : '');
    }
}

/**
 * Class ArrayDispatchTestController
 *
 * Stub controller for testing [Controller::class, 'method'] array dispatch.
 *
 * @package Tests\Routing
 */
class ArrayDispatchTestController
{
    /**
     * @param Request $request
     *
     * @return string
     */
    public function index(Request $request): string
    {
        return 'items-index';
    }
}

/**
 * Class ArrayDispatchTestContainer
 *
 * Minimal ContainerInterface stub that makes ArrayDispatchTestController.
 *
 * @package Tests\Routing
 */
class ArrayDispatchTestContainer implements ContainerInterface
{
    /**
     * @param string               $abstract
     * @param string|callable|null $factory
     *
     * @return static
     */
    public function bind(string $abstract, string|callable|null $factory = null): static
    {
        return $this;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     *
     * @return T
     */
    public function make(string $abstract): mixed
    {
        return new $abstract();
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @param T               $instance
     *
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
    }
}

/**
 * Minimal model stub whose static find() returns a new instance with the given id.
 *
 * @package Tests\Routing
 */
class ModelBindingStub
{
    /**
     * ModelBindingStub Constructor
     *
     * @param string $id
     */
    public function __construct(public readonly string $id = '')
    {
    }

    /**
     * @param string $id
     *
     * @return self|null
     */
    public static function find(string $id): ?self
    {
        return new self($id);
    }
}

/**
 * Stub whose static find() always returns null — simulates a missing model.
 *
 * @package Tests\Routing
 */
class ModelBindingNotFoundStub
{
    /**
     * @param string $id
     *
     * @return static|null
     */
    public static function find(string $id): ?static
    {
        return null;
    }
}
