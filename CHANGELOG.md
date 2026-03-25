# Changelog

All notable changes to `ez-php/framework` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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
