<?php

declare(strict_types=1);

namespace Tests\Container;

use EzPhp\Container\Container;
use EzPhp\Container\ContextualBindingBuilder;
use EzPhp\Exceptions\ContainerException;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionException;
use Tests\TestCase;

/**
 * Class ContainerTest
 *
 * @package Tests\Container
 */
#[CoversClass(Container::class)]
#[CoversClass(ContextualBindingBuilder::class)]
final class ContainerTest extends TestCase
{
    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_without_binding_instantiates_class(): void
    {
        $container = new Container();
        $result = $container->make(Container::class);
        $this->assertInstanceOf(Container::class, $result);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_returns_same_instance_on_second_call(): void
    {
        $container = new Container();
        $a = $container->make(Container::class);
        $b = $container->make(Container::class);
        $this->assertSame($a, $b);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_with_binding_uses_callable(): void
    {
        $container = new Container();
        $container->bind(Container::class, fn () => new Container());
        $result = $container->make(Container::class);
        $this->assertInstanceOf(Container::class, $result);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_bind_without_callable_uses_default_instantiation(): void
    {
        $container = new Container();
        $container->bind(Container::class);
        $result = $container->make(Container::class);
        $this->assertInstanceOf(Container::class, $result);
    }

    /**
     * @return void
     */
    public function test_bind_returns_self_for_chaining(): void
    {
        $container = new Container();
        $result = $container->bind(Container::class);
        $this->assertSame($container, $result);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_for_nonexistent_class(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $container->make('NonExistentClass\That\DoesNotExist'); // @phpstan-ignore-line
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_descriptive_exception_for_empty_class_name(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Class name must not be empty.');
        $container->make(''); // @phpstan-ignore-line
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_descriptive_exception_for_empty_class_name_with_overrides(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Class name must not be empty.');
        $container->make('', ['param' => 'value']); // @phpstan-ignore-line
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_autowiring_resolves_class_dependency(): void
    {
        $container = new Container();
        $result = $container->make(AutowiringDependent::class);

        $this->assertInstanceOf(AutowiringDependent::class, $result);
        $this->assertInstanceOf(AutowiringService::class, $result->service);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_autowiring_resolves_nested_dependencies(): void
    {
        $container = new Container();
        $result = $container->make(AutowiringOuter::class);

        $this->assertInstanceOf(AutowiringOuter::class, $result);
        $this->assertInstanceOf(AutowiringDependent::class, $result->dependent);
        $this->assertInstanceOf(AutowiringService::class, $result->dependent->service);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_autowiring_uses_default_value_for_optional_parameter(): void
    {
        $container = new Container();
        $result = $container->make(AutowiringOptional::class);

        $this->assertSame('default', $result->value);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_autowiring_throws_for_unresolvable_primitive(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot resolve parameter');
        $container->make(AutowiringUnresolvable::class);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_bind_interface_to_concrete_class(): void
    {
        $container = new Container();
        $container->bind(ContainerTestInterface::class, ContainerTestImplementation::class);

        $result = $container->make(ContainerTestInterface::class);

        $this->assertInstanceOf(ContainerTestImplementation::class, $result);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_bind_interface_resolves_concrete_dependencies(): void
    {
        $container = new Container();
        $container->bind(ContainerTestInterface::class, ContainerTestWithDependency::class);

        $result = $container->make(ContainerTestInterface::class);

        $this->assertInstanceOf(ContainerTestWithDependency::class, $result);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_instance_overrides_cached_singleton(): void
    {
        $container = new Container();
        $original = new AutowiringService();
        $container->bind(AutowiringService::class, fn () => $original);
        $container->make(AutowiringService::class); // cache the original

        $replacement = new AutowiringService();
        $container->instance(AutowiringService::class, $replacement);

        $this->assertSame($replacement, $container->make(AutowiringService::class));
    }

    // -------------------------------------------------------------------------
    // Contextual binding
    // -------------------------------------------------------------------------

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_contextual_binding_injects_specific_implementation(): void
    {
        $container = new Container();
        $container->when(ContextualServiceA::class)
            ->needs(ContextualLoggerInterface::class)
            ->give(ContextualFileLogger::class);

        $service = $container->make(ContextualServiceA::class);

        $this->assertInstanceOf(ContextualFileLogger::class, $service->logger);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_different_concretes_receive_different_contextual_implementations(): void
    {
        $container = new Container();
        $container->when(ContextualServiceA::class)
            ->needs(ContextualLoggerInterface::class)
            ->give(ContextualFileLogger::class);
        $container->when(ContextualServiceB::class)
            ->needs(ContextualLoggerInterface::class)
            ->give(ContextualNullLogger::class);

        $serviceA = $container->make(ContextualServiceA::class);
        $serviceB = $container->make(ContextualServiceB::class);

        $this->assertInstanceOf(ContextualFileLogger::class, $serviceA->logger);
        $this->assertInstanceOf(ContextualNullLogger::class, $serviceB->logger);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_contextual_binding_falls_back_to_regular_binding_for_other_classes(): void
    {
        $container = new Container();
        $container->bind(ContextualLoggerInterface::class, ContextualNullLogger::class);
        $container->when(ContextualServiceA::class)
            ->needs(ContextualLoggerInterface::class)
            ->give(ContextualFileLogger::class);

        $serviceA = $container->make(ContextualServiceA::class);
        $serviceB = $container->make(ContextualServiceB::class);

        $this->assertInstanceOf(ContextualFileLogger::class, $serviceA->logger);
        $this->assertInstanceOf(ContextualNullLogger::class, $serviceB->logger);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_contextual_binding_give_accepts_callable_factory(): void
    {
        $custom = new ContextualFileLogger();

        $container = new Container();
        $container->when(ContextualServiceA::class)
            ->needs(ContextualLoggerInterface::class)
            ->give(fn (Container $c): ContextualFileLogger => $custom);

        $service = $container->make(ContextualServiceA::class);

        $this->assertSame($custom, $service->logger);
    }

    // -------------------------------------------------------------------------
    // Tagged services
    // -------------------------------------------------------------------------

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_tag_and_makeTagged_resolve_all_tagged_services(): void
    {
        $container = new Container();
        $container->tag([TaggedHandlerA::class, TaggedHandlerB::class], 'handlers');

        $instances = $container->makeTagged('handlers');

        $this->assertCount(2, $instances);
        $this->assertInstanceOf(TaggedHandlerA::class, $instances[0]);
        $this->assertInstanceOf(TaggedHandlerB::class, $instances[1]);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_makeTagged_returns_empty_array_for_unknown_tag(): void
    {
        $container = new Container();

        $this->assertSame([], $container->makeTagged('unknown'));
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_tag_accepts_single_class_string(): void
    {
        $container = new Container();
        $container->tag(TaggedHandlerA::class, 'handlers');

        $instances = $container->makeTagged('handlers');

        $this->assertCount(1, $instances);
        $this->assertInstanceOf(TaggedHandlerA::class, $instances[0]);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_tag_called_multiple_times_accumulates_services(): void
    {
        $container = new Container();
        $container->tag(TaggedHandlerA::class, 'handlers');
        $container->tag(TaggedHandlerB::class, 'handlers');
        $container->tag(TaggedHandlerC::class, 'handlers');

        $instances = $container->makeTagged('handlers');

        $this->assertCount(3, $instances);
        $this->assertInstanceOf(TaggedHandlerA::class, $instances[0]);
        $this->assertInstanceOf(TaggedHandlerB::class, $instances[1]);
        $this->assertInstanceOf(TaggedHandlerC::class, $instances[2]);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_makeTagged_resolves_via_regular_bindings(): void
    {
        $container = new Container();
        $container->bind(TaggedHandlerA::class, fn () => new TaggedHandlerA());
        $container->tag(TaggedHandlerA::class, 'handlers');

        $instances = $container->makeTagged('handlers');

        $this->assertInstanceOf(TaggedHandlerA::class, $instances[0]);
    }

    // -------------------------------------------------------------------------
    // alias()
    // -------------------------------------------------------------------------

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_alias_registers_binding_for_abstract(): void
    {
        $container = new Container();
        $container->alias(ContainerTestInterface::class, ContainerTestImplementation::class);

        $result = $container->make(ContainerTestInterface::class);

        $this->assertInstanceOf(ContainerTestImplementation::class, $result);
    }

    /**
     * @return void
     */
    public function test_alias_returns_self_for_chaining(): void
    {
        $container = new Container();
        $result = $container->alias(ContainerTestInterface::class, ContainerTestImplementation::class);

        $this->assertSame($container, $result);
    }

    // -------------------------------------------------------------------------
    // make() with overrides
    // -------------------------------------------------------------------------

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_with_overrides_injects_provided_values(): void
    {
        $container = new Container();
        $result = $container->make(AutowiringOptional::class, ['value' => 'overridden']);

        $this->assertSame('overridden', $result->value);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_with_overrides_skips_singleton_cache(): void
    {
        $container = new Container();
        $a = $container->make(AutowiringOptional::class, ['value' => 'first']);
        $b = $container->make(AutowiringOptional::class, ['value' => 'second']);

        $this->assertSame('first', $a->value);
        $this->assertSame('second', $b->value);
        $this->assertNotSame($a, $b);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_without_overrides_still_caches_singleton(): void
    {
        $container = new Container();
        $a = $container->make(AutowiringOptional::class);
        $b = $container->make(AutowiringOptional::class);

        $this->assertSame($a, $b);
    }

    // ─── build() — circular dependency detection ─────────────────────────────

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_for_circular_dependency(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Circular dependency detected/');
        $container->make(CircularA::class);
    }

    /**
     * The error message must include the full dependency chain so developers can
     * identify which classes form the cycle.
     *
     * @return void
     * @throws ReflectionException
     */
    public function test_circular_dependency_exception_contains_chain(): void
    {
        $container = new Container();

        try {
            $container->make(CircularA::class);
            $this->fail('Expected ContainerException');
        } catch (ContainerException $e) {
            $this->assertStringContainsString('CircularA', $e->getMessage());
            $this->assertStringContainsString('CircularB', $e->getMessage());
        }
    }

    // ─── build() — non-instantiable types ────────────────────────────────────

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_for_unbound_interface(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Interface 'Tests\\Container\\ContainerTestInterface' cannot be instantiated directly.");
        $container->make(ContainerTestInterface::class);
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_make_throws_for_unbound_abstract_class(): void
    {
        $container = new Container();
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage("Abstract class 'Tests\\Container\\AbstractContainerStub' cannot be instantiated directly.");
        $container->make(AbstractContainerStub::class);
    }
}

/**
 * Class AutowiringService
 *
 * @package Tests\Container
 */
class AutowiringService
{
}

/**
 * Class AutowiringDependent
 *
 * @package Tests\Container
 */
readonly class AutowiringDependent
{
    /**
     * AutowiringDependent Constructor
     *
     * @param AutowiringService $service
     */
    public function __construct(public AutowiringService $service)
    {
    }
}

/**
 * Class AutowiringOuter
 *
 * @package Tests\Container
 */
readonly class AutowiringOuter
{
    /**
     * AutowiringOuter Constructor
     *
     * @param AutowiringDependent $dependent
     */
    public function __construct(public AutowiringDependent $dependent)
    {
    }
}

/**
 * Class AutowiringOptional
 *
 * @package Tests\Container
 */
readonly class AutowiringOptional
{
    /**
     * AutowiringOptional Constructor
     *
     * @param string $value
     */
    public function __construct(public string $value = 'default')
    {
    }
}

/**
 * Class AutowiringUnresolvable
 *
 * @package Tests\Container
 */
readonly class AutowiringUnresolvable
{
    /**
     * AutowiringUnresolvable Constructor
     *
     * @param string $required
     */
    public function __construct(public string $required)
    {
    }
}

/**
 * Interface ContainerTestInterface
 *
 * @package Tests\Container
 */
interface ContainerTestInterface
{
}

/**
 * Class ContainerTestImplementation
 *
 * @package Tests\Container
 */
class ContainerTestImplementation implements ContainerTestInterface
{
}

/**
 * Class ContainerTestWithDependency
 *
 * @package Tests\Container
 */
readonly class ContainerTestWithDependency implements ContainerTestInterface
{
    /**
     * ContainerTestWithDependency Constructor
     *
     * @param AutowiringService $service
     */
    public function __construct(public AutowiringService $service)
    {
    }
}

/**
 * Interface ContextualLoggerInterface
 *
 * @package Tests\Container
 */
interface ContextualLoggerInterface
{
}

/**
 * Class ContextualFileLogger
 *
 * @package Tests\Container
 */
class ContextualFileLogger implements ContextualLoggerInterface
{
}

/**
 * Class ContextualNullLogger
 *
 * @package Tests\Container
 */
class ContextualNullLogger implements ContextualLoggerInterface
{
}

/**
 * Class ContextualServiceA
 *
 * @package Tests\Container
 */
readonly class ContextualServiceA
{
    /**
     * ContextualServiceA Constructor
     *
     * @param ContextualLoggerInterface $logger
     */
    public function __construct(public ContextualLoggerInterface $logger)
    {
    }
}

/**
 * Class ContextualServiceB
 *
 * @package Tests\Container
 */
readonly class ContextualServiceB
{
    /**
     * ContextualServiceB Constructor
     *
     * @param ContextualLoggerInterface $logger
     */
    public function __construct(public ContextualLoggerInterface $logger)
    {
    }
}

/**
 * Class TaggedHandlerA
 *
 * @package Tests\Container
 */
class TaggedHandlerA
{
}

/**
 * Class TaggedHandlerB
 *
 * @package Tests\Container
 */
class TaggedHandlerB
{
}

/**
 * Class TaggedHandlerC
 *
 * @package Tests\Container
 */
class TaggedHandlerC
{
}

/**
 * Class AbstractContainerStub
 *
 * @package Tests\Container
 */
abstract class AbstractContainerStub
{
}

/**
 * Class CircularA
 *
 * Used to test circular-dependency detection in the Container.
 * CircularA → CircularB → CircularA forms the cycle.
 *
 * @package Tests\Container
 */
class CircularA
{
    /**
     * CircularA Constructor
     *
     * @param CircularB $b
     */
    public function __construct(public CircularB $b)
    {
    }
}

/**
 * Class CircularB
 *
 * Used to test circular-dependency detection in the Container.
 * CircularB → CircularA → CircularB forms the cycle.
 *
 * @package Tests\Container
 */
class CircularB
{
    /**
     * CircularB Constructor
     *
     * @param CircularA $a
     */
    public function __construct(public CircularA $a)
    {
    }
}
