<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Database\Database;

/**
 * Interface SeederInterface
 *
 * All seeder files in database/seeders/ must return an anonymous class
 * implementing this interface.
 *
 * @package EzPhp\Migration
 */
interface SeederInterface
{
    /**
     * Populate the database with seed data.
     *
     * @param Database $db
     *
     * @return void
     */
    public function run(Database $db): void;
}
