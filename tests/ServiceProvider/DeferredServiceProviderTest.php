<?php

declare(strict_types=1);

namespace Tests\ServiceProvider;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class DeferredServiceProviderTest
 *
 * @package Tests\ServiceProvider
 */
#[CoversClass(Application::class)]
#[UsesClass(ServiceProvider::class)]
#[UsesClass(Container::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]
#[UsesClass(ConsoleServiceProvider::class)]
final class DeferredServiceProviderTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        DeferredTestProvider::reset();
        EagerTestProvider::reset();
    }

    /**
     * Deferred provider's register() must NOT be called during bootstrap.
     *
     * @return void
     */
    public function test_deferred_provider_is_not_registered_at_bootstrap(): void
    {
        $app = new Application();
        $app->register(DeferredTestProvider::class);
        $app->bootstrap();

        $this->assertFalse(DeferredTestProvider::$registered);
    }

    /**
     * Deferred provider's register() and boot() are called on first make().
     *
     * @return void
     */
    public function test_deferred_provider_is_lazily_registered_on_first_make(): void
    {
        $app = new Application();
        $app->register(DeferredTestProvider::class);
        $app->bootstrap();

        $this->assertFalse(DeferredTestProvider::$registered);

        $app->make(DeferredTestService::class);

        $this->assertTrue(DeferredTestProvider::$registered);
        $this->assertTrue(DeferredTestProvider::$booted);
    }

    /**
     * Deferred provider must not be activated more than once.
     *
     * @return void
     */
    public function test_deferred_provider_is_not_loaded_twice(): void
    {
        $app = new Application();
        $app->register(DeferredTestProvider::class);
        $app->bootstrap();

        $app->make(DeferredTestService::class);
        $app->make(DeferredTestService::class);

        $this->assertSame(1, DeferredTestProvider::$registerCount);
    }

    /**
     * Non-deferred providers continue to be registered eagerly.
     *
     * @return void
     */
    public function test_non_deferred_provider_is_registered_at_bootstrap(): void
    {
        $app = new Application();
        $app->register(EagerTestProvider::class);
        $app->bootstrap();

        $this->assertTrue(EagerTestProvider::$registered);
    }

    /**
     * Deferred providers return the right default values for their contracts.
     *
     * @return void
     */
    public function test_service_provider_deferred_defaults_to_false(): void
    {
        $app = new Application();
        $app->bootstrap();

        $provider = new class ($app) extends ServiceProvider {};

        $this->assertFalse($provider->deferred());
        $this->assertSame([], $provider->provides());
    }

    /**
     * When one deferred provider's own boot() resolves a class belonging to a
     * second, not-yet-loaded deferred provider, the nested provider's register()
     * must still run immediately (so the binding is available), but its boot()
     * must run only after the outer provider's own boot() has completed —
     * not interleaved in the middle of it.
     *
     * @return void
     */
    public function test_nested_deferred_activation_boots_after_outer_provider(): void
    {
        NestedOuterProvider::reset();
        NestedInnerProvider::reset();

        $app = new Application();
        $app->register(NestedOuterProvider::class);
        $app->register(NestedInnerProvider::class);
        $app->bootstrap();

        $app->make(NestedOuterService::class);

        $this->assertTrue(NestedOuterProvider::$booted);
        $this->assertTrue(NestedInnerProvider::$registered);
        $this->assertTrue(NestedInnerProvider::$booted);

        // The inner provider's register() ran while the outer was still
        // mid-boot (so the binding existed for the outer's own make() call),
        // but its boot() only ran once the outer's boot() had fully returned.
        $this->assertTrue(NestedOuterProvider::$innerRegisteredDuringOuterBoot);
        $this->assertFalse(NestedOuterProvider::$innerBootedDuringOuterBoot);
    }
}

/**
 * @internal test helper
 */
final class NestedOuterService
{
}

/**
 * @internal test helper
 */
final class NestedInnerService
{
}

/**
 * Deferred provider whose boot() resolves a class belonging to another,
 * not-yet-loaded deferred provider (NestedInnerProvider).
 *
 * @internal test helper
 */
final class NestedOuterProvider extends ServiceProvider
{
    public static bool $booted = false;

    public static bool $innerRegisteredDuringOuterBoot = false;

    public static bool $innerBootedDuringOuterBoot = false;

    public static function reset(): void
    {
        self::$booted = false;
        self::$innerRegisteredDuringOuterBoot = false;
        self::$innerBootedDuringOuterBoot = false;
    }

    public function deferred(): bool
    {
        return true;
    }

    public function provides(): array
    {
        return [NestedOuterService::class];
    }

    public function register(): void
    {
        $this->app->bind(NestedOuterService::class, fn (): NestedOuterService => new NestedOuterService());
    }

    public function boot(): void
    {
        // Triggers activation of NestedInnerProvider from inside this
        // provider's own boot() — the scenario under test.
        $this->app->make(NestedInnerService::class);

        self::$innerRegisteredDuringOuterBoot = NestedInnerProvider::$registered;
        self::$innerBootedDuringOuterBoot = NestedInnerProvider::$booted;

        self::$booted = true;
    }
}

/**
 * @internal test helper
 */
final class NestedInnerProvider extends ServiceProvider
{
    public static bool $registered = false;

    public static bool $booted = false;

    public static function reset(): void
    {
        self::$registered = false;
        self::$booted = false;
    }

    public function deferred(): bool
    {
        return true;
    }

    public function provides(): array
    {
        return [NestedInnerService::class];
    }

    public function register(): void
    {
        self::$registered = true;
        $this->app->bind(NestedInnerService::class, fn (): NestedInnerService => new NestedInnerService());
    }

    public function boot(): void
    {
        self::$booted = true;
    }
}

/**
 * Minimal service class registered by the deferred provider.
 *
 * @internal test helper
 */
final class DeferredTestService
{
    //
}

/**
 * A deferred service provider that registers DeferredTestService.
 *
 * @internal test helper
 */
final class DeferredTestProvider extends ServiceProvider
{
    public static bool $registered = false;

    public static bool $booted = false;

    public static int $registerCount = 0;

    /**
     * @return void
     */
    public static function reset(): void
    {
        self::$registered = false;
        self::$booted = false;
        self::$registerCount = 0;
    }

    /**
     * @return bool
     */
    public function deferred(): bool
    {
        return true;
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
        return [DeferredTestService::class];
    }

    /**
     * @return void
     */
    public function register(): void
    {
        self::$registered = true;
        self::$registerCount++;

        $this->app->bind(
            DeferredTestService::class,
            fn (): DeferredTestService => new DeferredTestService(),
        );
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        self::$booted = true;
    }
}

/**
 * A non-deferred (eager) provider used to verify eager loading still works.
 *
 * @internal test helper
 */
final class EagerTestProvider extends ServiceProvider
{
    public static bool $registered = false;

    /**
     * @return void
     */
    public static function reset(): void
    {
        self::$registered = false;
    }

    /**
     * @return void
     */
    public function register(): void
    {
        self::$registered = true;
    }
}
