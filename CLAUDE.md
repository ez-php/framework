# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/framework

The framework core — runtime kernel, dependency injection, routing, middleware, configuration, database, migrations, exception handling, and scaffolding console commands.

---

## Source Structure

```
src/
├── Application/
│   ├── Application.php               — Runtime kernel; bootstrap, request handling, DI façade
│   └── CoreServiceProviders.php      — Ordered list of built-in service providers
├── Config/
│   ├── Config.php                    — Immutable dot-notation config store
│   ├── ConfigLoader.php              — Scans config/*.php and returns keyed array
│   └── ConfigServiceProvider.php    — Binds ConfigLoader and Config to the container
├── Console/
│   ├── ConsoleServiceProvider.php    — Registers Console, Scheduler, and all built-in commands
│   ├── Command/
│   │   ├── MakeControllerCommand.php — Scaffolds a controller class in src/Controllers/
│   │   ├── MakeMigrationCommand.php  — Creates a timestamped migration stub
│   │   ├── MakeMiddlewareCommand.php — Scaffolds a middleware class in src/Middleware/
│   │   ├── MakeProviderCommand.php   — Scaffolds a service provider in src/Providers/
│   │   ├── MigrateCommand.php        — Runs all pending migrations
│   │   ├── MigrateRollbackCommand.php — Rolls back the last migration batch
│   │   ├── MigrateFreshCommand.php   — Rolls back all migrations and re-runs them from scratch
│   │   ├── MigrateStatusCommand.php  — Shows the status of all migrations (pending / ran)
│   │   ├── ScheduleRunCommand.php    — Runs all due scheduled commands (trigger with system cron)
│   │   ├── ServeCommand.php          — Starts the built-in PHP web server
│   │   ├── TinkerCommand.php         — Opens an interactive REPL with the application bootstrapped (requires psy/psysh)
│   │   └── ListCommand.php           — Lists all available commands
│   └── Schedule/
│       ├── Scheduler.php             — Registry of ScheduledCommands; command() adds entries; dueCommands() filters by time
│       └── ScheduledCommand.php      — A command + frequency predicate; everyMinute/hourly/daily/weekly/monthly
├── Container/
│   └── Container.php                 — DI container with singleton cache and autowiring
├── Database/
│   ├── Database.php                  — Thin PDO wrapper with transactions and QueryBuilder bridge
│   └── DatabaseServiceProvider.php  — Configures and registers the Database instance
├── Exceptions/
│   ├── ApplicationException.php      — General application-level exception
│   ├── ConfigException.php           — Config loading/access errors
│   ├── ContainerException.php        — DI resolution errors
│   ├── DefaultExceptionHandler.php   — Converts exceptions to HTTP responses (debug + JSON support)
│   ├── ExceptionHandler.php          — Interface: render(Throwable, Request) → Response
│   ├── ExceptionHandlerServiceProvider.php — Registers DefaultExceptionHandler with debug flag
│   ├── EzPhpException.php            — Base exception for all framework exceptions
│   └── RouteException.php            — Thrown when no route matches (default: "Route not found")
├── Middleware/
│   ├── CorsMiddleware.php            — Adds CORS headers; returns 204 for OPTIONS
│   ├── MiddlewareHandler.php         — Builds and executes global + route middleware pipelines
│   ├── MiddlewareInterface.php       — Contract: handle(Request, callable) → Response
│   └── TerminableMiddleware.php      — Extension: terminate(Request, Response) → void
├── Migration/
│   ├── MigrationInterface.php        — Contract: up(PDO) and down(PDO)
│   ├── MigrationServiceProvider.php  — Registers Migrator with correct path
│   └── Migrator.php                  — Scans, loads, executes, and rolls back migrations
├── Routing/
│   ├── Route.php                     — Single route: regex matching, param extraction, handler execution
│   ├── Router.php                    — Route registry with group support and named URL generation
│   └── RouterServiceProvider.php    — Binds Router and loads routes/web.php
└── ServiceProvider/
    └── ServiceProvider.php           — Abstract base: register() + boot() two-phase lifecycle

tests/
├── TestCase.php                      — Base PHPUnit test case
├── DatabaseTestCase.php              — Swaps DB_DATABASE to testing DB for each test
├── TestTest.php                      — Smoke test
├── Application/ApplicationTest.php
├── Config/ConfigLoaderTest.php
├── Config/ConfigTest.php
├── Console/Command/MakeCommandsTest.php
├── Console/Command/MakeMigrationCommandTest.php
├── Console/Command/MigrateCommandTest.php
├── Console/Command/MigrateRollbackCommandTest.php
├── Console/ConsoleServiceProviderTest.php
├── Container/ContainerTest.php
├── Database/DatabaseServiceProviderTest.php
├── Database/DatabaseTest.php
├── Exceptions/DefaultExceptionHandlerTest.php
├── Exceptions/ExceptionHandlerServiceProviderTest.php
├── Middleware/CorsMiddlewareTest.php
├── Middleware/MiddlewareHandlerTest.php
├── Middleware/TerminableMiddlewareTest.php
├── Migration/MigrationServiceProviderTest.php
├── Migration/MigratorTest.php
├── Routing/RouterServiceProviderTest.php
├── Routing/RouterTest.php
├── ServiceProvider/CoreServiceProvidersTest.php
└── ServiceProvider/ServiceProviderTest.php
```

---

## Key Classes and Responsibilities

### Application (`src/Application/Application.php`)

Central runtime kernel. Orchestrates the entire request lifecycle.

- `bootstrap()` — Idempotent; calls `foundation()`, then load/register/boot service providers
- `handle(Request)` → `Response` — Dispatches through middleware pipeline; catches exceptions via `ExceptionHandler`
- `make(class)` — Delegates to Container; returns singleton instance
- `bind(class, value)` — Delegates to Container; wraps callables transparently
- `register(ServiceProvider::class)` — Queues a user provider (must be called before bootstrap)
- `middleware(MiddlewareInterface::class)` — Adds a class-string to the global middleware stack
- `basePath(string)` → `string` — Resolves a path relative to the project root

Bootstrap is **idempotent** — safe to call multiple times; subsequent calls are no-ops.

---

### Container (`src/Container/Container.php`)

Resolution order: cached instance → registered binding → autowire → `ContainerException`.

- Bindings are stored as closures; calling `bind()` with a class-string or null auto-wraps it
- Autowiring uses `ReflectionClass` to recursively resolve constructor parameters
- Interfaces and abstract classes **cannot** be autowired — bind them explicitly in a provider

---

### Router (`src/Routing/Router.php`) and Route (`src/Routing/Route.php`)

- `{param}` segments compile to `([^/]+)` (required)
- `/{param?}` compiles to `(?:\/([^/]+))?` (optional, with preceding slash)
- HTTP method override: POST field `_method` overrides the real method
- Duplicate route detection on registration
- `group(prefix, callback)` supports nested prefixes via a stack

---

### MiddlewareHandler (`src/Middleware/MiddlewareHandler.php`)

Builds a recursive closure pipeline from class-strings. Resolves each middleware via the container at call time. Tracks resolved instances to call `terminate()` on `TerminableMiddleware` after the response is sent.

- `handle(Route, Request)` — Runs the full pipeline: global → route-level → handler
- `dispatch(Request, callable)` — Runs global middleware only (used in tests / partial dispatch)
- `terminate(Request, Response)` — Called after `ResponseEmitter::emit()`

---

### Database (`src/Database/Database.php`)

Thin PDO wrapper. Not a DBAL. The ORM and query builder live in `ez-php/orm`.

- `query(sql, bindings)` — Positional bindings with type detection (null/bool/int/string)
- `transaction(callable)` — Auto-rollback on exception; returns callable's return value
- `table(name)` — Returns a `QueryBuilder` from `ez-php/orm` (cross-package bridge point)

---

### Migrator (`src/Migration/Migrator.php`)

- Scans `database/migrations/*.php`; each file must `return` an anonymous class implementing `MigrationInterface`
- Tracks executed migrations and batch numbers in the `migrations` table (auto-created)
- `migrate()` — Runs pending files in filename order; wraps each in a transaction
- `rollback()` — Reverses the latest batch in reverse filename order

---

### ExceptionHandler (`src/Exceptions/`)

`DefaultExceptionHandler` handles two concerns:

1. **Status mapping:** `RouteException` → 404, everything else → 500
2. **Response format:** Checks `Accept: application/json` for JSON; otherwise plain text
3. **Debug mode:** Shows real exception message on 500; production hides it as "Internal Server Error"

---

## Design Decisions and Constraints

- **`final` everywhere** — All concrete classes are `final` to prevent unintended inheritance. Extend behavior through composition or new service providers.
- **Idempotent bootstrap** — `Application::bootstrap()` guards via `$booted`. Safe to call in tests without leaking state.
- **Service providers instantiated directly** — `new $class($this)`, not via the container, to avoid circular bootstrap dependency.
- **`Application::bind()` wraps callables** — Ensures the Application is always injected into user bindings, decoupling the user from the Container API.
- **Router does not execute middleware** — Separation of concerns: `Router::retrieveRoute()` only resolves; `MiddlewareHandler` executes.
- **Database is intentionally minimal** — No query builder, no schema builder in this package. Those belong in `ez-php/orm`.
- **`Database::table()` depends on `ez-php/orm`** — The only cross-module dependency in the core. Acceptable as ORM is a sibling package in the monorepo.
- **Migrations use raw PDO** — `up(PDO)` / `down(PDO)` to keep migrations dependency-free; they must not rely on the ORM or Database class.
- **`CorsMiddleware` is a concrete helper** — Provided as a convenience; not part of the routing or middleware infrastructure.

---

## Testing Approach

- **Unit tests** — All subsystems except Database and Migrator can be tested without a real DB. Use in-memory fakes or mock the PDO layer where possible.
- **Database tests** — `DatabaseTestCase` swaps `DB_DATABASE` to the `ez-php_testing` database for the duration of each test. Requires a running MySQL container and the testing database to exist.
- **Infrastructure requirement** — `DatabaseTest` and `MigratorTest` require MySQL 8.4 (via Docker). They will fail without the `ez-php_testing` database.
- **No mocking of the Container or Application** — Tests bootstrap real instances to catch wiring bugs early.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Indirectly used classes must be declared via `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| HTTP Request / Response value objects | `ez-php/http` |
| `.env` file parsing | `ez-php/dotenv` |
| Console / Command infrastructure | `ez-php/console` |
| ORM, Query Builder, Schema Builder | `ez-php/orm` |
| Authentication (session / token) | `ez-php/auth` |
| Caching (file, array, Redis) | `ez-php/cache` |
| Event bus | `ez-php/events` |
| Input validation | `ez-php/validation` |
| HTTP client (cURL) | `ez-php/http-client` |
| Translations / i18n | `ez-php/i18n` |
| Application template / entry point | `ez-php/` |
