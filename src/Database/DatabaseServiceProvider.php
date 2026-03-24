<?php

declare(strict_types=1);

namespace EzPhp\Database;

use EzPhp\Application\Application;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigValidator;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class DatabaseServiceProvider
 *
 * @internal
 * @package EzPhp\Database
 */
final class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Database::class, function (Application $app): Database {
            $config = $app->make(Config::class);

            $driver = $config->string('db.driver', 'mysql');

            ConfigValidator::assertValidValue('db.driver', $driver, ['mysql', 'sqlite']);

            $database = $config->string('db.database');
            $username = $config->string('db.username', '');
            $password = $config->string('db.password', '');

            if ($driver === 'sqlite') {
                $dsn = "sqlite:$database";

                return new Database($dsn, '', '');
            }

            $host = $config->string('db.host');
            $port = $config->string('db.port');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database,
            );

            return new Database($dsn, $username, $password);
        });

        $this->app->bind(DatabaseInterface::class, fn () => $this->app->make(Database::class));
    }
}
