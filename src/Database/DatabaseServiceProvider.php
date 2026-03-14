<?php

declare(strict_types=1);

namespace EzPhp\Database;

use EzPhp\Application\Application;
use EzPhp\Config\Config;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class DatabaseServiceProvider
 *
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

            /** @var string $driver */
            $driver = $config->get('db.driver', 'mysql');
            /** @var string $database */
            $database = $config->get('db.database');
            /** @var string $username */
            $username = $config->get('db.username', '');
            /** @var string $password */
            $password = $config->get('db.password', '');

            if ($driver === 'sqlite') {
                $dsn = "sqlite:$database";

                return new Database($dsn, '', '');
            }

            /** @var string $host */
            $host = $config->get('db.host');
            /** @var string $port */
            $port = $config->get('db.port');

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database,
            );

            return new Database($dsn, $username, $password);
        });
    }
}
