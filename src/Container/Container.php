<?php

declare(strict_types=1);

namespace EzPhp\Container;

use EzPhp\Exceptions\ContainerException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Class Container
 *
 * @package EzPhp\Container
 */
final class Container
{
    /**
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * Contextual bindings: concrete → abstract → factory.
     *
     * @var array<string, array<string, callable>>
     */
    private array $contextualBindings = [];

    /**
     * Tagged service collections: tag → list of class strings.
     *
     * @var array<string, list<string>>
     */
    private array $tags = [];

    /**
     * ReflectionClass cache — keyed by class name to avoid re-instantiating
     * Reflection on every container build call for the same class.
     *
     * @var array<class-string, ReflectionClass<object>>
     */
    private array $reflectionCache = [];

    /**
     * Resolution stack used for circular-dependency detection.
     * Keys are class names currently being built; the stack is unwound via finally.
     *
     * @var array<string, true>
     */
    private array $resolutionStack = [];

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param class-string|callable|null $value
     *
     * @return $this
     */
    public function bind(string $class, string|callable|null $value = null): self
    {
        if ($value === null) {
            $value = function () use ($class): object {
                return new $class();
            };
        } elseif (is_string($value)) {
            /** @var class-string $concrete */
            $concrete = $value;
            $value = function () use ($concrete): object {
                return $this->make($concrete);
            };
        }

        $this->bindings[$class] = $value;

        return $this;
    }

    /**
     * Register a short alias for an existing binding. Equivalent to `bind($abstract, $concrete)`.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param class-string    $concrete
     *
     * @return $this
     */
    public function alias(string $abstract, string $concrete): self
    {
        return $this->bind($abstract, $concrete);
    }

    /**
     * Register an existing object as a shared instance, bypassing bindings.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param T               $instance
     *
     * @return void
     */
    public function instance(string $class, object $instance): void
    {
        $this->instances[$class] = $instance;
    }

    /**
     * Resolve an instance of the given class from the container.
     *
     * When $overrides is non-empty, the singleton cache is bypassed and the
     * result is not stored. This allows ad-hoc construction with runtime values.
     *
     * Note: overrides are only available on the concrete Container class, not
     * via ContainerInterface (which declares make(string $class): object).
     *
     * @template T of object
     * @param class-string<T>      $class
     * @param array<string, mixed> $overrides Named constructor parameter overrides.
     *
     * @return T
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function make(string $class, array $overrides = []): object
    {
        if (trim($class) === '') {
            throw new ContainerException('Class name must not be empty.');
        }

        if (!class_exists($class) && !interface_exists($class)) {
            throw new ContainerException("Class '$class' does not exist");
        }

        if ($overrides !== []) {
            /** @var T */
            return $this->build($class, $overrides);
        }

        if (isset($this->instances[$class])) {
            /** @var T */
            return $this->instances[$class];
        }

        if (isset($this->bindings[$class])) {
            /** @var T */
            return $this->instances[$class] = ($this->bindings[$class])($this);
        }

        /** @var T */
        return $this->instances[$class] = $this->build($class);
    }

    /**
     * Begin a contextual binding for the given concrete class.
     *
     * @param class-string $concrete
     *
     * @return ContextualBindingBuilder
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Register a contextual binding factory.
     * Called internally by {@see ContextualBindingBuilder::give()}.
     *
     * @internal Called by ContextualBindingBuilder; use $container->when()->needs()->give() instead.
     *
     * @param string   $concrete
     * @param string   $abstract
     * @param callable $factory
     *
     * @return void
     */
    public function addContextualBinding(string $concrete, string $abstract, callable $factory): void
    {
        $this->contextualBindings[$concrete][$abstract] = $factory;
    }

    /**
     * Tag one or more class strings under a named group.
     *
     * @param list<class-string>|class-string $abstracts
     * @param string                          $tag
     *
     * @return void
     */
    public function tag(array|string $abstracts, string $tag): void
    {
        if (is_string($abstracts)) {
            $abstracts = [$abstracts];
        }

        if (!array_key_exists($tag, $this->tags)) {
            $this->tags[$tag] = [];
        }

        foreach ($abstracts as $abstract) {
            $this->tags[$tag][] = $abstract;
        }
    }

    /**
     * Resolve and return all services registered under the given tag.
     *
     * @param string $tag
     *
     * @return list<object>
     * @throws ReflectionException
     */
    public function makeTagged(string $tag): array
    {
        $instances = [];

        foreach ($this->tags[$tag] ?? [] as $abstract) {
            /** @var class-string $abstract */
            $instances[] = $this->make($abstract);
        }

        return $instances;
    }

    /**
     * @template T of object
     * @param class-string<T>      $class
     * @param array<string, mixed> $overrides Named constructor parameter overrides.
     *
     * @return T
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function build(string $class, array $overrides = []): object
    {
        if (isset($this->resolutionStack[$class])) {
            $chain = implode(' → ', array_keys($this->resolutionStack)) . ' → ' . $class;
            throw new ContainerException(
                "Circular dependency detected while resolving '$class': $chain"
            );
        }

        $this->resolutionStack[$class] = true;

        try {
            return $this->doBuild($class, $overrides);
        } finally {
            unset($this->resolutionStack[$class]);
        }
    }

    /**
     * @template T of object
     * @param class-string<T>      $class
     * @param array<string, mixed> $overrides Named constructor parameter overrides.
     *
     * @return T
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function doBuild(string $class, array $overrides = []): object
    {
        if (!isset($this->reflectionCache[$class])) {
            $this->reflectionCache[$class] = new ReflectionClass($class);
        }

        $reflection = $this->reflectionCache[$class];

        if (!$reflection->isInstantiable()) {
            $kind = $reflection->isInterface() ? 'Interface' : 'Abstract class';
            throw new ContainerException(
                "$kind '$class' cannot be instantiated directly. " .
                "Bind a concrete implementation via \$app->bind('$class', ConcreteClass::class) in a service provider."
            );
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $paramName = $parameter->getName();

            if (array_key_exists($paramName, $overrides)) {
                $dependencies[] = $overrides[$paramName];
                continue;
            }

            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                /** @var class-string */
                $typeName = $type->getName();

                if (isset($this->contextualBindings[$class][$typeName])) {
                    $dependencies[] = ($this->contextualBindings[$class][$typeName])($this);
                    continue;
                }

                $dependencies[] = $this->make($typeName);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContainerException(
                "Cannot resolve parameter '\${$paramName}' of class '$class'"
            );
        }

        /** @var T */
        return $reflection->newInstanceArgs($dependencies);
    }
}
