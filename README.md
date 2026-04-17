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

- [ez-php/orm](https://github.com/ez-php/orm) — Data Mapper ORM, Query Builder, Schema Builder
- [ez-php/auth](https://github.com/ez-php/auth) — Session, Bearer token, JWT, and personal access token authentication
- [ez-php/cache](https://github.com/ez-php/cache) — Array, File, Redis, and Memcached drivers; tags, locks, stampede protection
- [ez-php/events](https://github.com/ez-php/events) — Synchronous event bus, static `Event` façade, stoppable events
- [ez-php/i18n](https://github.com/ez-php/i18n) — Locale-based translator, dot-notation keys, `LocaleFormatter`
- [ez-php/validation](https://github.com/ez-php/validation) — Rule-based validator, `FormRequest`, optional DB/translator integration
- [ez-php/http-client](https://github.com/ez-php/http-client) — Fluent cURL HTTP client, static `Http` façade, pluggable transport
- [ez-php/logging](https://github.com/ez-php/logging) — Structured logger, File/JSON/Stack/Null drivers, `RequestContextMiddleware`
- [ez-php/mail](https://github.com/ez-php/mail) — Transactional email, SMTP/Mailgun/SendGrid/Log/Null drivers
- [ez-php/view](https://github.com/ez-php/view) — PHP template engine, layouts, sections, partials
- [ez-php/queue](https://github.com/ez-php/queue) — Async job queue, database and Redis drivers, failed-job management
- [ez-php/rate-limiter](https://github.com/ez-php/rate-limiter) — Rate limiting, Array/Redis/CacheDelegate drivers, `ThrottleMiddleware`
- [ez-php/scheduler](https://github.com/ez-php/scheduler) — Cron-based job scheduler with File/Database mutex overlap prevention
- [ez-php/broadcast](https://github.com/ez-php/broadcast) — Real-time event broadcasting, SSE helpers, Null/Log/Redis/Array drivers
- [ez-php/search](https://github.com/ez-php/search) — Full-text search, Meilisearch/Elasticsearch/Typesense drivers
- [ez-php/notification](https://github.com/ez-php/notification) — Multi-channel notifications (mail, broadcast, database)
- [ez-php/storage](https://github.com/ez-php/storage) — File storage abstraction, Local and S3 drivers
- [ez-php/health](https://github.com/ez-php/health) — `/health` endpoint with DB, Redis, and Queue probes
- [ez-php/feature-flags](https://github.com/ez-php/feature-flags) — Feature flag evaluation, File/Database/Array drivers
- [ez-php/audit](https://github.com/ez-php/audit) — Event-driven audit log, `AuditLogger`, `AuditQuery`
- [ez-php/metrics](https://github.com/ez-php/metrics) — Prometheus metrics endpoint, Counter/Gauge/Histogram
- [ez-php/websocket](https://github.com/ez-php/websocket) — RFC 6455 WebSocket server, PHP 8.5 Fibers
- [ez-php/bignum](https://github.com/ez-php/bignum) — Arbitrary-precision integers and decimals
- [ez-php/graphql](https://github.com/ez-php/graphql) — GraphQL endpoint, SchemaBuilder
- [ez-php/money](https://github.com/ez-php/money) — Monetary values, Currency, CurrencyRegistry
- [ez-php/opcache](https://github.com/ez-php/opcache) — OPcache preloading
- [ez-php/openapi](https://github.com/ez-php/openapi) — OpenAPI 3.x spec generation, attribute-driven
- [ez-php/two-factor](https://github.com/ez-php/two-factor) — TOTP two-factor authentication

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
