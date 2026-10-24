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
| `Routing` | Router with named routes, route groups, and URL parameter support |
| `Controller` | `Controller` / `ApiController` base classes with validation and JSON response helpers, plus `authorize(?GateInterface, $ability, $subject)` (403 via `ForbiddenException`; needs a `GateInterface` binding such as `ez-php/authorization`) |
| `Middleware` | Pipeline-based middleware with terminable support, plus bundled middleware: `CsrfMiddleware`, `XsrfCookieMiddleware` (SPAs), `CorsMiddleware`, `SecurityHeadersMiddleware`, `ConditionalGetMiddleware`, `LocaleNegotiationMiddleware`, `MaintenanceModeMiddleware` |
| `Maintenance` | `down` / `up` maintenance mode with a secret bypass path |
| `Database` | Thin PDO wrapper with transactions |
| `Migration` | File-based migration runner with batch rollback |
| `Config` | Dot-notation config loader |
| `Console` | Built-in commands (`migrate`, `make:*`, `route:*`, …) on top of `ez-php/console` |
| `Exceptions` | Exception handler with service provider integration |

HTTP value objects (`Request`, `Response`, `RequestFactory`) come from [ez-php/http](https://github.com/ez-php/http) and `.env` loading from [ez-php/dotenv](https://github.com/ez-php/dotenv); translation from [ez-php/i18n](https://github.com/ez-php/i18n) (`TranslatorServiceProvider` is a core provider; error pages are localised) and input validation from [ez-php/validation](https://github.com/ez-php/validation) (`Controller::validate()`). Together with [ez-php/contracts](https://github.com/ez-php/contracts) and [ez-php/console](https://github.com/ez-php/console) these are required dependencies, not part of this package. CSRF setup (session, token store, SPA cookie) is described in [CLAUDE.md § CSRF Protection](CLAUDE.md#csrf-protection).

## Quick start

```php
$app = new \EzPhp\Application\Application(basePath: __DIR__);
$app->register(AppServiceProvider::class);
$app->bootstrap();

$request = \EzPhp\Http\RequestFactory::createFromGlobals();
$app->send($request, $app->handle($request));
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

### Route middleware, aliases and parameters

Middleware is registered by name — a class, an alias or a group — and resolved from the
container when the request runs. Append `:param1,param2` to pass per-route values to a
middleware implementing `EzPhp\Contracts\ParameterizedMiddlewareInterface`:

```php
$app->middlewareAlias('can', \EzPhp\Authorization\CanMiddleware::class); // before bootstrap

$router->post('/posts', [PostController::class, 'store'])
    ->middleware(AuthMiddleware::class)
    ->middleware('can:create,App\Entities\Post'); // handle($request, $next, 'create', 'App\Entities\Post')
```

Registrations stay plain strings, so `route:cache` and `route:list` keep them as written.
Parameters sent to a middleware that does not implement the interface are an error.

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

The framework runs without any of these; add the ones an application needs.

- [ez-php/ai](https://github.com/ez-php/ai) — Multi-provider AI client; OpenAI, Anthropic, Gemini, Mistral, Grok drivers; streaming; tool calling; embeddings; `Ai` static façade
- [ez-php/ai-media](https://github.com/ez-php/ai-media) — Image generation, audio transcription and text-to-speech via OpenAI/Gemini drivers
- [ez-php/asyncapi](https://github.com/ez-php/asyncapi) — AsyncAPI 3.0 document generation for broadcast channels, webhooks and WebSocket messages; attribute-driven
- [ez-php/audit](https://github.com/ez-php/audit) — Event-driven audit log; `AuditLogger`, `AuditQuery`; no ORM kernel changes
- [ez-php/auth](https://github.com/ez-php/auth) — Session, Bearer token, JWT and personal access token authentication, static `Auth` façade
- [ez-php/authorization](https://github.com/ez-php/authorization) — Gate and policy based authorization; `CanMiddleware`
- [ez-php/bignum](https://github.com/ez-php/bignum) — Arbitrary-precision integers and decimals; `BigInteger`, `BigDecimal`; BCMath and GMP backends
- [ez-php/broadcast](https://github.com/ez-php/broadcast) — Real-time event broadcasting, Null/Log/Redis/Array/WebSocket drivers (SSE via `StreamedResponse::sse()` in `ez-php/http`)
- [ez-php/cache](https://github.com/ez-php/cache) — Array, File, Redis, Memcached, Database and APCu cache drivers with TTL support, key prefixes, `cache:clear`/`cache:prune`
- [ez-php/dataloader](https://github.com/ez-php/dataloader) — Generic keyed-batch loading and per-key memoization primitive for solving N+1 access patterns
- [ez-php/event-store](https://github.com/ez-php/event-store) — Append-only event store with stream replay and projections for event sourcing
- [ez-php/event-store-snapshots](https://github.com/ez-php/event-store-snapshots) — Snapshots for `ez-php/event-store` streams — PDO/in-memory stores, every-N-events policy, snapshot-aware loader
- [ez-php/events](https://github.com/ez-php/events) — Synchronous event bus, static `Event` façade, closure and object listeners
- [ez-php/exchange](https://github.com/ez-php/exchange) — Currency exchange-rate lookup and conversion for `Money` values; `ExchangeRateProviderInterface`, `StaticExchangeRateProvider`, `HttpExchangeRateProvider`, `Converter`
- [ez-php/feature-flags](https://github.com/ez-php/feature-flags) — Feature flag evaluation; File, Database, Redis, Array, and Rollout drivers; `Flag::enabled()` static façade
- [ez-php/graphql](https://github.com/ez-php/graphql) — GraphQL endpoint; `SchemaBuilder`, `GraphQLExecutor`, `GraphQLController`
- [ez-php/graphql-subscriptions](https://github.com/ez-php/graphql-subscriptions) — GraphQL subscriptions over `ez-php/websocket` (`graphql-transport-ws`), fed by `ez-php/broadcast` events
- [ez-php/health](https://github.com/ez-php/health) — `/health` endpoint with DB, Redis, Queue, RedisQueue, OPcache and disk-space probes (plus opt-in cache and HTTP probes); JSON response
- [ez-php/http-client](https://github.com/ez-php/http-client) — Fluent cURL HTTP client, static `Http` façade, fake transport seam
- [ez-php/idempotency](https://github.com/ez-php/idempotency) — `Idempotency-Key` middleware for safe POST retries
- [ez-php/json-schema](https://github.com/ez-php/json-schema) — JSON Schema generation from PHP classes; `SchemaGenerator`, `#[Property]`/`#[Ignore]` attributes
- [ez-php/json-schema-validator](https://github.com/ez-php/json-schema-validator) — Validates decoded JSON against the schema subset `ez-php/json-schema` generates; JSON Pointer error paths
- [ez-php/log-transport](https://github.com/ez-php/log-transport) — Async/remote log shipping (ELK/NDJSON, Datadog, Loki, New Relic, Splunk HEC, Graylog GELF) drivers for `ez-php/logging`
- [ez-php/logging](https://github.com/ez-php/logging) — Structured logger, File/Stdout/Syslog/Json/Stack/MinLevel/Null drivers, `LoggingExceptionHandler` decorator
- [ez-php/mail](https://github.com/ez-php/mail) — Transactional email, SMTP/Mailgun/SendGrid/Log/Null drivers
- [ez-php/media](https://github.com/ez-php/media) — Image resizing and file processing; transformations over a `StorageInterface` source via `ext-gd` or `ext-imagick`
- [ez-php/metrics](https://github.com/ez-php/metrics) — Prometheus metrics endpoint; Counter, Gauge, Histogram; `Metrics` static façade; `GET /metrics`
- [ez-php/metrics-statsd](https://github.com/ez-php/metrics-statsd) — StatsD/UDP exporter for `ez-php/metrics`
- [ez-php/money](https://github.com/ez-php/money) — Monetary values; `Money`, `Currency`, `CurrencyRegistry`; decimal and intl formatters/parsers
- [ez-php/notification](https://github.com/ez-php/notification) — Multi-channel notifications (mail, broadcast, database, push, sms, webhook channels; `RateLimitedChannel` decorator); optional queue-backed async delivery
- [ez-php/oauth](https://github.com/ez-php/oauth) — OAuth2/SSO client — authorization code flow with PKCE and state, OpenID Connect ID token validation (discovery, JWKS)
- [ez-php/opcache](https://github.com/ez-php/opcache) — OPcache preloading; `Preloader`, `PreloadConfig`, `PreloaderServiceProvider`
- [ez-php/openapi](https://github.com/ez-php/openapi) — OpenAPI 3.x spec generation; `OpenApiGenerator`, `SchemaBuilder`; PHP attribute-driven
- [ez-php/orm](https://github.com/ez-php/orm) — Data Mapper ORM (`Entity` + `AbstractRepository`), Query Builder, Schema Builder, relations, soft deletes
- [ez-php/otel](https://github.com/ez-php/otel) — OpenTelemetry-style spans and OTLP/HTTP JSON export, no vendor SDK
- [ez-php/psr-bridge](https://github.com/ez-php/psr-bridge) — PSR interop — PSR-7 conversion, PSR-15 middleware and PSR-3 logger adapters in both directions
- [ez-php/push](https://github.com/ez-php/push) — Mobile and browser push notifications (APNS, FCM, Web Push) as an `ez-php/notification` channel
- [ez-php/qrcode](https://github.com/ez-php/qrcode) — Dependency-free QR code encoder (versions 1–40, L/M/Q/H) with SVG and PNG renderers
- [ez-php/queue](https://github.com/ez-php/queue) — Async job queue, database and Redis drivers, `Worker`; `queue:work`, `queue:failed`, `queue:monitor`, `queue:schedule` commands
- [ez-php/rate-limiter](https://github.com/ez-php/rate-limiter) — Rate limiting, Array/File/Redis/SlidingWindowRedis/Cache (`CacheDriver`) drivers, `ThrottleMiddleware`
- [ez-php/scheduler](https://github.com/ez-php/scheduler) — Cron-based job scheduler with File/Database/DatabaseWithExpiry/Redis/Cache mutex overlap prevention; `SchedulerServiceProvider`; `scheduler:run` / `scheduler:list` commands (distinct from the framework's mutex-less `schedule:run`)
- [ez-php/search](https://github.com/ez-php/search) — Full-text search, Meilisearch/Elasticsearch/Typesense/Database (MySQL FULLTEXT, SQLite FTS5) drivers, `search:import`/`search:flush`
- [ez-php/session](https://github.com/ez-php/session) — Session handler drivers (File/Database/Redis/Cache/Array), `StartSessionMiddleware`, flash data, ID regeneration
- [ez-php/sms](https://github.com/ez-php/sms) — SMS delivery — Twilio, Vonage, Log and Null drivers over `ez-php/http-client`, `Sms` static façade
- [ez-php/storage](https://github.com/ez-php/storage) — File storage abstraction; Local, S3 and Google Cloud Storage drivers (plus an in-memory test driver); AWS Signature V4, GCS V4 signed URLs
- [ez-php/support](https://github.com/ez-php/support) — General-purpose utility classes; `Range`, `WeightedRandom`, `TimeProbability`, `DailyQuota`, `XpProgression`, `Coordinate`, `DimensionSet`, `InfiniteGrid`, `CronExpression`; zero external dependencies
- [ez-php/swagger-ui](https://github.com/ez-php/swagger-ui) — Serves Swagger UI / ReDoc documentation for the OpenAPI spec produced by `ez-php/openapi`
- [ez-php/testing](https://github.com/ez-php/testing) — `EntityFactory`, `TestResponse`, recording fakes (`FakeQueue`, `FakeMailer`, `FakeChannel`, `EventSpy`, `FakeStorage`) — framework-independent test utilities (dev-only)
- [ez-php/testing-application](https://github.com/ez-php/testing-application) — `ApplicationTestCase`, `DatabaseTestCase`, `HttpTestCase` — framework-coupled test base classes (dev-only)
- [ez-php/two-factor](https://github.com/ez-php/two-factor) — TOTP two-factor authentication; `TwoFactorManager`, `TwoFactorMiddleware`, `Base32`, `Totp`
- [ez-php/view](https://github.com/ez-php/view) — PHP template engine, layouts, sections, partials, HTML escaping
- [ez-php/view-cache](https://github.com/ez-php/view-cache) — Output-caching decorator for `ez-php/view`'s `ViewEngine`, with mtime-based invalidation
- [ez-php/webauthn](https://github.com/ez-php/webauthn) — WebAuthn / FIDO2 passkey authentication: registration and assertion ceremonies, attestation verification, challenge handling
- [ez-php/webauthn-metadata](https://github.com/ez-php/webauthn-metadata) — FIDO MDS trust-anchor lookups for `ez-php/webauthn`
- [ez-php/webhook](https://github.com/ez-php/webhook) — Outgoing HMAC-signed webhooks as a retrying queue job, plus incoming-signature middleware
- [ez-php/websocket](https://github.com/ez-php/websocket) — RFC 6455 WebSocket server; PHP 8.5 Fibers; `Server`, `ChannelManager`, `ConnectionInterface`, `LoopHooks` (extra read streams, ticks)
- [ez-php/websocket-client](https://github.com/ez-php/websocket-client) — RFC 6455 WebSocket client, plain-TCP and TLS (wss://) transports
- [ez-php/websocket-tls](https://github.com/ez-php/websocket-tls) — TLS/WSS termination for `ez-php/websocket`; a `tcp://` listener (certificate in the stream context) handing negotiated connections to the plain WebSocket server

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
