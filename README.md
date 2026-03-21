# ez-php/framework

Lightweight PHP framework core — dependency injection, routing, middleware, and database migrations.

[![CI](https://github.com/ez-php/framework/actions/workflows/ci.yml/badge.svg)](https://github.com/ez-php/framework/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ext-pdo

## Installation

```bash
composer require ez-php/framework
```

## What's included

| Module | Description |
|--------|-------------|
| `Application` | Runtime kernel, bootstrap lifecycle, service provider loading |
| `Container` | Dependency injection with autowiring |
| `ServiceProvider` | Register/boot pattern for modular configuration |
| `Http` | Immutable `Request` / `Response`, `RequestFactory` |
| `Routing` | Router with named routes, route groups, and URL parameter support |
| `Middleware` | Pipeline-based middleware with terminable support |
| `Database` | Thin PDO wrapper with transactions |
| `Migration` | File-based migration runner with batch rollback |
| `Config` | Dot-notation config loader |
| `Console` | CLI kernel with command registration |
| `Env` | `.env` file parser with variable interpolation |
| `Exceptions` | Exception handler with service provider integration |

## Quick start

```php
$app = new \EzPhp\Application\Application(basePath: __DIR__);
$app->register(AppServiceProvider::class);
$app->bootstrap();

$response = $app->handle(\EzPhp\Http\RequestFactory::fromGlobals());
(new \EzPhp\Http\ResponseEmitter())->emit($response);
```

## Routing

### Basic routes

```php
$router->get('/users', fn (Request $r): Response => ...);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id}', fn (Request $r): Response => ...);
$router->patch('/users/{id}', fn (Request $r): Response => ...);
$router->delete('/users/{id}', fn (Request $r): Response => ...);
```

### Route groups

```php
$router->group('/admin', function (Router $r): void {
    $r->get('/dashboard', fn (): Response => ...);
    $r->get('/users', fn (): Response => ...);
}, middleware: [AuthMiddleware::class]);
```

### Named routes and URL generation

```php
$router->get('/users/{id}', fn (): Response => ...)->name('users.show');

$url = $router->route('users.show', ['id' => 42]); // '/users/42'
```

### HTTP method override (`_method`)

HTML forms only support `GET` and `POST`. To send `PUT`, `PATCH`, or `DELETE` from a form, add a hidden `_method` field to a `POST` form:

```html
<form method="POST" action="/users/42">
    <input type="hidden" name="_method" value="DELETE">
    <!-- … -->
</form>
```

The router reads `_method` from the parsed request body and overrides the HTTP method before route matching.

> **Security note:** `_method` is only evaluated for `POST` requests. Because it is read from the parsed body (`$_POST`), it is only effective when the form is submitted as `application/x-www-form-urlencoded` or `multipart/form-data`. JSON requests and requests with other content types are **not** affected.

## Optional modules

- [ez-php/orm](https://github.com/ez-php/orm) — Active Record ORM with query builder and schema builder
- [ez-php/auth](https://github.com/ez-php/auth) — Authentication
- [ez-php/cache](https://github.com/ez-php/cache) — Cache drivers (array, file, Redis)
- [ez-php/events](https://github.com/ez-php/events) — Event dispatcher
- [ez-php/i18n](https://github.com/ez-php/i18n) — Internationalisation
- [ez-php/validation](https://github.com/ez-php/validation) — Validator
- [ez-php/http-client](https://github.com/ez-php/http-client) — HTTP client

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
