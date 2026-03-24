<?php

declare(strict_types=1);

namespace EzPhp\Container;

/**
 * Class ContextualBindingBuilder
 *
 * Fluent builder returned by {@see Container::when()}.
 *
 * Usage:
 *   $container->when(ServiceA::class)
 *             ->needs(LoggerInterface::class)
 *             ->give(FileLogger::class);
 *
 * @internal
 * @package EzPhp\Container
 */
final class ContextualBindingBuilder
{
    /**
     * @var string
     */
    private string $abstract = '';

    /**
     * ContextualBindingBuilder Constructor
     *
     * @param Container $container
     * @param string    $concrete
     */
    public function __construct(
        private readonly Container $container,
        private readonly string $concrete,
    ) {
    }

    /**
     * Specify the abstract type (interface or class) that the concrete depends on.
     *
     * @param class-string $abstract
     *
     * @return $this
     */
    public function needs(string $abstract): self
    {
        $this->abstract = $abstract;

        return $this;
    }

    /**
     * Specify what to inject when the declared dependency is resolved.
     *
     * Accepts either a class-string (resolved via the container) or a callable
     * factory that receives the Container and returns the implementation.
     *
     * @param class-string|callable $implementation
     *
     * @return void
     */
    public function give(string|callable $implementation): void
    {
        if (is_string($implementation)) {
            /** @var class-string $implementation */
            $class = $implementation;
            $this->container->addContextualBinding(
                $this->concrete,
                $this->abstract,
                fn (Container $container): object => $container->make($class),
            );

            return;
        }

        $this->container->addContextualBinding($this->concrete, $this->abstract, $implementation);
    }
}
