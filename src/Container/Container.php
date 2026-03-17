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
     * @template T of object
     * @param class-string<T> $class
     *
     * @return T
     * @throws ReflectionException
     */
    public function make(string $class): object
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new ContainerException("Class '$class' does not exist");
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
     * @param class-string<T> $class
     *
     * @return T
     * @throws ContainerException
     * @throws ReflectionException
     */
    private function build(string $class): object
    {
        $reflection = new ReflectionClass($class);

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
                "Cannot resolve parameter '\${$parameter->getName()}' of class '$class'"
            );
        }

        /** @var T */
        return $reflection->newInstanceArgs($dependencies);
    }
}
