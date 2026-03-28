# Changelog

All notable changes to `ez-php/framework` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Changed
- `CorsMiddleware`, `CsrfMiddleware`, and `DebugToolbarMiddleware` — `handle()` now accepts `RequestInterface` instead of `Request`, satisfying the updated `MiddlewareInterface` contract
- `DefaultExceptionHandler` and `DebugHtmlRenderer` — `render()` now accepts `RequestInterface`; custom renderers registered via `renderable()` must accept `RequestInterface` as well
- `Application::back()` — parameter widened to `RequestInterface`
- `Router::isCsrfExemptRoute()` — parameter widened to `RequestInterface`
- `MiddlewareHandler` — middleware group entries are now typed as `class-string<MiddlewareInterface>` throughout (property, `setGroups()`, `expandGroups()`); same change applied to `Application::middlewareGroup()` and `getMiddlewareGroups()`
- Updated `ez-php/contracts` and `ez-php/http` dependency constraints to `^1.2`

---

## [v1.1.0] — 2026-03-28

### Added
- `make:model` command — scaffolds an Active Record model in `app/Models/`
- `make:event` command — scaffolds an event class in `app/Events/`
- `make:listener` command — scaffolds an event listener in `app/Listeners/`
- `make:request` command — scaffolds a form request class in `app/Requests/`
- `make:test` command — scaffolds a test class (unit/feature/http) in `tests/`
- `ide:generate` command — generates `_ide_helpers.php` PHPDoc stubs for installed static façades
- `DebugToolbarMiddleware` — auto-registered by `ExceptionHandlerServiceProvider` when `APP_DEBUG=true`; injects a fixed HTML toolbar with status code, method/URI, latency, and peak memory
- Container scoping — `Container::scope()` creates a child container for isolated, request-scoped service resolution
- Circular dependency detection in `Container` — throws `ContainerException` with the full dependency chain instead of a stack overflow

### Changed
- `ServeCommand` — added `--watch` flag; polls `app/`, `config/`, and `routes/` for PHP file changes and auto-restarts the built-in server
- `DatabaseServiceProvider` — validates `db.host`, `db.database`, and `db.username` at bootstrap; throws `ConfigException` with a clear message instead of a cryptic PDO error

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `Application` — runtime kernel; idempotent `bootstrap()` with `foundation()`, `loadServiceProviders()`, `registerServiceProviders()`, and `bootServiceProviders()` phases
- `Container` — dependency injection container with explicit bindings, autowiring via Reflection, and singleton caching; resolution order: cached → binding → autowire → `ContainerException`
- `ServiceProvider` — two-phase register/boot lifecycle; core providers loaded via `CoreServiceProviders::all()`
- `Router` — dynamic route matching on method and URI with `{param}` segment extraction; group support with prefix and middleware
- `MiddlewareHandler` — chain-of-responsibility pipeline; supports terminable middleware and global vs. route-level middleware stacks
- `Database` — thin PDO wrapper with `query()`, `execute()`, `beginTransaction()`, `commit()`, and `rollback()`
- `Migrator` — scans `database/migrations/`, tracks executed migrations and batch numbers in the `migrations` table; supports up, down, and batch rollback
- `Config` — dot-notation configuration backed by PHP arrays and environment variables; immutable at runtime
- `ExceptionHandler` — switches between debug (full stack trace) and production (clean HTML) error renderers
- `CorsMiddleware` — configurable CORS headers for cross-origin requests
- Core console commands: `migrate`, `migrate:rollback`, `migrate:fresh`, `migrate:status`, `make:controller`, `make:migration`, `make:middleware`, `make:provider`, `serve`, `tinker`, `list`
- Six core service providers (always loaded in order): `ConfigServiceProvider`, `DatabaseServiceProvider`, `MigrationServiceProvider`, `RouterServiceProvider`, `ExceptionHandlerServiceProvider`, `ConsoleServiceProvider`
