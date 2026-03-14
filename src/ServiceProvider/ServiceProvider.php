<?php

declare(strict_types=1);

namespace EzPhp\ServiceProvider;

use EzPhp\Application\Application;

/**
 * Class ServiceProvider
 *
 * @package EzPhp\ServiceProvider
 */
abstract class ServiceProvider
{
    /**
     * ServiceProvider Constructor
     *
     * @param Application $app
     */
    final public function __construct(
        protected Application $app
    ) {
        //
    }

    /**
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
