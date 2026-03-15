<?php

declare(strict_types=1);

namespace EzPhp\Config;

use EzPhp\Application\Application;
use EzPhp\Contracts\ConfigInterface;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class ConfigServiceProvider
 *
 * @package EzPhp\Config
 */
final class ConfigServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(ConfigLoader::class, fn (Application $app) => new ConfigLoader($app->basePath('config')));
        $this->app->bind(Config::class, fn (Application $app) => new Config($app->make(ConfigLoader::class)->load()));
        $this->app->bind(ConfigInterface::class, fn () => $this->app->make(Config::class));
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
