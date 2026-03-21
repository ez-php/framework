<?php

declare(strict_types=1);

namespace EzPhp\Application;

use RuntimeException;

/**
 * Class StaticFacade
 *
 * Abstract base class for static façade helpers (e.g. Auth, Log, Http, RateLimiter).
 *
 * The pattern is: each subclass holds a static instance of its concrete type,
 * set by the service provider via setInstance(), and exposed for test reset via
 * resetInstance().
 *
 * IMPORTANT: Subclasses MUST redeclare the static property with their concrete type
 * to avoid cross-class contamination via PHP's static property inheritance:
 *
 * ```php
 * final class MyFacade extends StaticFacade
 * {
 *     protected static MyService|null $instance = null;
 * }
 * ```
 *
 * Late static binding (`static::$instance`) ensures each subclass's own property
 * is accessed, but PHP only separates properties per class when they are explicitly
 * redeclared in the subclass.
 *
 * @package EzPhp\Application
 */
abstract class StaticFacade
{
    /**
     * The underlying concrete instance. Must be redeclared in each subclass
     * with the appropriate concrete type to avoid cross-class contamination.
     *
     * @var object|null
     */
    protected static object|null $instance = null;

    /**
     * Set the underlying instance. Called by the service provider during boot.
     *
     * @param object $instance
     *
     * @return void
     */
    public static function setInstance(object $instance): void
    {
        static::$instance = $instance;
    }

    /**
     * Return the underlying instance.
     *
     * @return object
     * @throws RuntimeException If no instance has been set.
     */
    public static function getInstance(): object
    {
        if (static::$instance === null) {
            throw new RuntimeException(
                static::class . ' has no instance set. Call ' . static::class . '::setInstance() first.'
            );
        }

        return static::$instance;
    }

    /**
     * Clear the stored instance. Primarily useful in tests to reset state
     * between test cases.
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }
}
