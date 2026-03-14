<?php

declare(strict_types=1);

namespace Tests\Container;

use EzPhp\Container\Container;
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
