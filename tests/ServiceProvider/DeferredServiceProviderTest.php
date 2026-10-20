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
use EzPhp\Exceptions\ContainerException;
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

    /**
     * The same guarantee holds when the nested activation happens inside the
     * outer provider's register(): the inner provider registers immediately
     * but boots only after the outer provider's boot() has returned.
     *
     * @return void
     */
    public function test_nested_deferred_activation_from_register_boots_after_outer_provider(): void
    {
        ActivationLog::reset();
        NestedInnerProvider::reset();

        $app = new Application();
        $app->register(RegisterNestingOuterProvider::class);
        $app->register(NestedInnerProvider::class);
        $app->bootstrap();

        $app->make(RegisterNestingOuterService::class);

        $this->assertTrue(NestedInnerProvider::$registered);
        $this->assertTrue(NestedInnerProvider::$booted);
        $this->assertSame(['outer:register', 'outer:boot', 'inner:boot'], ActivationLog::$entries);
    }

    /**
     * A provider whose register() throws is not marked as loaded, so the next
     * resolution of one of its classes tries again.
     *
     * @return void
     */
    public function test_provider_whose_register_throws_is_retried_on_next_resolution(): void
    {
        FlakyRegisterProvider::reset();

        $app = new Application();
        $app->register(FlakyRegisterProvider::class);
        $app->bootstrap();

        try {
            $app->make(FlakyRegisterService::class);
            $this->fail('the first activation should throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('register failed', $e->getMessage());
        }

        $this->assertInstanceOf(FlakyRegisterService::class, $app->make(FlakyRegisterService::class));
        $this->assertSame(2, FlakyRegisterProvider::$attempts);
    }

    /**
     * When an outer provider's boot() throws after pulling in a nested provider,
     * the queued nested boot is discarded — a later, unrelated activation must
     * not boot it as a side effect.
     *
     * @return void
     */
    public function test_failed_boot_does_not_leak_queued_boots_into_a_later_activation(): void
    {
        NestedInnerProvider::reset();
        DeferredTestProvider::reset();

        $app = new Application();
        $app->register(ThrowingBootOuterProvider::class);
        $app->register(NestedInnerProvider::class);
        $app->register(DeferredTestProvider::class);
        $app->bootstrap();

        try {
            $app->make(ThrowingBootOuterService::class);
            $this->fail('the outer boot() should throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('boot failed', $e->getMessage());
        }

        $app->make(DeferredTestService::class);

        $this->assertTrue(DeferredTestProvider::$booted);
        $this->assertTrue(NestedInnerProvider::$registered);
        $this->assertFalse(NestedInnerProvider::$booted);
    }

    /**
     * Autowiring a class whose constructor needs a deferred binding must
     * activate the provider — regression: only a top-level Application::make()
     * of the deferred class itself did, so the nested resolution threw
     * "Interface ... cannot be instantiated directly".
     *
     * @return void
     */
    public function test_autowired_dependency_on_a_deferred_binding_activates_the_provider(): void
    {
        DeferredContractProvider::reset();

        $app = new Application();
        $app->register(DeferredContractProvider::class);
        $app->bootstrap();

        $consumer = $app->make(DeferredContractConsumer::class);

        $this->assertInstanceOf(DeferredContractImplementation::class, $consumer->contract);
        $this->assertTrue(DeferredContractProvider::$registered);
        $this->assertTrue(DeferredContractProvider::$booted);
    }

    /**
     * An optional parameter typed against a deferred binding receives the
     * binding, not its default.
     *
     * @return void
     */
    public function test_optional_dependency_on_a_deferred_binding_is_resolved(): void
    {
        DeferredContractProvider::reset();

        $app = new Application();
        $app->register(DeferredContractProvider::class);
        $app->bootstrap();

        $consumer = $app->make(DeferredOptionalContractConsumer::class);

        $this->assertInstanceOf(DeferredContractImplementation::class, $consumer->contract);
    }

    /**
     * A scope() child sees deferred bindings activated on the root before it was
     * created; it does not activate providers itself (the binding would land in
     * the root container, not in the child's copied bindings).
     *
     * @return void
     */
    public function test_scope_sees_deferred_bindings_activated_before_it_was_created(): void
    {
        DeferredContractProvider::reset();

        $app = new Application();
        $app->register(DeferredContractProvider::class);
        $app->bootstrap();
        $container = $app->make(Container::class);

        $this->assertFalse($container->scope()->has(DeferredContractInterface::class));

        $app->make(DeferredContractInterface::class);
        $consumer = $container->scope()->make(DeferredContractConsumer::class);

        $this->assertInstanceOf(DeferredContractImplementation::class, $consumer->contract);
    }

    /**
     * scope() does not carry the deferred resolver: make() on a scope for an
     * interface whose deferred provider has not been activated yet throws
     * instead of activating the provider — activate on the root first.
     *
     * @return void
     */
    public function test_scope_does_not_activate_a_not_yet_activated_deferred_provider(): void
    {
        DeferredContractProvider::reset();

        $app = new Application();
        $app->register(DeferredContractProvider::class);
        $app->bootstrap();
        $scope = $app->make(Container::class)->scope();

        try {
            $scope->make(DeferredContractInterface::class);
            $this->fail('a scope must not resolve a deferred binding that was never activated');
        } catch (ContainerException) {
            $this->assertFalse(DeferredContractProvider::$registered);
        }
    }

    /**
     * has() reports a deferred binding without activating its provider.
     *
     * @return void
     */
    public function test_has_reports_deferred_bindings_without_activating(): void
    {
        DeferredContractProvider::reset();

        $app = new Application();
        $app->register(DeferredContractProvider::class);
        $app->bootstrap();

        $this->assertTrue($app->has(DeferredContractInterface::class));
        $this->assertTrue($app->make(Container::class)->has(DeferredContractInterface::class));
        $this->assertFalse(DeferredContractProvider::$registered);
    }
}

/**
 * @internal test helper
 */
interface DeferredContractInterface
{
}

/**
 * @internal test helper
 */
final class DeferredContractImplementation implements DeferredContractInterface
{
}

/**
 * Autowired consumer whose constructor needs the deferred binding.
 *
 * @internal test helper
 */
final readonly class DeferredContractConsumer
{
    public function __construct(public DeferredContractInterface $contract)
    {
    }
}

/**
 * Autowired consumer with an optional dependency on the deferred binding.
 *
 * @internal test helper
 */
final readonly class DeferredOptionalContractConsumer
{
    public function __construct(public ?DeferredContractInterface $contract = null)
    {
    }
}

/**
 * Deferred provider binding an interface, resolved only through autowiring.
 *
 * @internal test helper
 */
final class DeferredContractProvider extends ServiceProvider
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
        return [DeferredContractInterface::class];
    }

    public function register(): void
    {
        self::$registered = true;
        $this->app->bind(DeferredContractInterface::class, DeferredContractImplementation::class);
    }

    public function boot(): void
    {
        self::$booted = true;
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
        ActivationLog::$entries[] = 'inner:boot';
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

/**
 * Records the order of provider phases across the nesting tests.
 *
 * @internal test helper
 */
final class ActivationLog
{
    /** @var list<string> */
    public static array $entries = [];

    public static function reset(): void
    {
        self::$entries = [];
    }
}

/**
 * @internal test helper
 */
final class RegisterNestingOuterService
{
}

/**
 * Deferred provider whose register() resolves a class of another, not-yet-loaded
 * deferred provider (NestedInnerProvider).
 *
 * @internal test helper
 */
final class RegisterNestingOuterProvider extends ServiceProvider
{
    public function deferred(): bool
    {
        return true;
    }

    public function provides(): array
    {
        return [RegisterNestingOuterService::class];
    }

    public function register(): void
    {
        $this->app->bind(RegisterNestingOuterService::class, fn (): RegisterNestingOuterService => new RegisterNestingOuterService());
        $this->app->make(NestedInnerService::class);
        ActivationLog::$entries[] = 'outer:register';
    }

    public function boot(): void
    {
        ActivationLog::$entries[] = NestedInnerProvider::$booted ? 'outer:boot-after-inner' : 'outer:boot';
    }
}

/**
 * @internal test helper
 */
final class FlakyRegisterService
{
}

/**
 * Deferred provider whose first register() throws.
 *
 * @internal test helper
 */
final class FlakyRegisterProvider extends ServiceProvider
{
    public static int $attempts = 0;

    public static function reset(): void
    {
        self::$attempts = 0;
    }

    public function deferred(): bool
    {
        return true;
    }

    public function provides(): array
    {
        return [FlakyRegisterService::class];
    }

    public function register(): void
    {
        if (++self::$attempts === 1) {
            throw new \RuntimeException('register failed');
        }

        $this->app->bind(FlakyRegisterService::class, fn (): FlakyRegisterService => new FlakyRegisterService());
    }
}

/**
 * @internal test helper
 */
final class ThrowingBootOuterService
{
}

/**
 * Deferred provider whose boot() pulls in NestedInnerProvider, then throws.
 *
 * @internal test helper
 */
final class ThrowingBootOuterProvider extends ServiceProvider
{
    public function deferred(): bool
    {
        return true;
    }

    public function provides(): array
    {
        return [ThrowingBootOuterService::class];
    }

    public function register(): void
    {
        $this->app->bind(ThrowingBootOuterService::class, fn (): ThrowingBootOuterService => new ThrowingBootOuterService());
    }

    public function boot(): void
    {
        $this->app->make(NestedInnerService::class);

        throw new \RuntimeException('boot failed');
    }
}
