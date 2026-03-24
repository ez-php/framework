<?php

declare(strict_types=1);

namespace EzPhp\Application;

use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\I18n\TranslatorServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Routing\RouterServiceProvider;

/**
 * Class CoreServiceProviders
 *
 * @internal
 * @package EzPhp\Application
 */
final class CoreServiceProviders
{
    /**
     * @return list<class-string<\EzPhp\Contracts\ServiceProvider>>
     */
    public static function all(): array
    {
        return [
            ConfigServiceProvider::class,
            TranslatorServiceProvider::class,
            DatabaseServiceProvider::class,
            MigrationServiceProvider::class,
            RouterServiceProvider::class,
            ExceptionHandlerServiceProvider::class,
            ConsoleServiceProvider::class,
        ];
    }
}
