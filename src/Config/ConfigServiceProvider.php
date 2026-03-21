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

        $this->app->bind(Config::class, function (Application $app): Config {
            $cachePath = $app->basePath('bootstrap/cache/config.php');

            if (is_file($cachePath)) {
                $cached = require $cachePath;

                if (is_array($cached)) {
                    /** @var array<string, mixed> $cached */
                    return new Config($cached);
                }
            }

            return new Config($app->make(ConfigLoader::class)->load());
        });

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
