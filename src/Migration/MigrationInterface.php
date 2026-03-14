<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use PDO;

/**
 * Interface MigrationInterface
 *
 * @package EzPhp\Migration
 */
interface MigrationInterface
{
    /**
     * @param PDO $pdo
     *
     * @return void
     */
    public function up(PDO $pdo): void;

    /**
     * @param PDO $pdo
     *
     * @return void
     */
    public function down(PDO $pdo): void;
}
