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
