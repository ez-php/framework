<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Contracts\DatabaseInterface;

/**
 * Interface SeederInterface
 *
 * All seeder files in database/seeders/ must return an anonymous class
 * implementing this interface.
 *
 * Typed against the DatabaseInterface contract, not the concrete Database
 * class (dependency inversion). Changed from `run(Database $db)` — a seeder
 * still declaring the concrete type no longer satisfies the interface; see
 * the template's docs/upgrade-seeder-interface.md.
 *
 * @package EzPhp\Migration
 */
interface SeederInterface
{
    /**
     * Populate the database with seed data.
     *
     * @param DatabaseInterface $db
     *
     * @return void
     */
    public function run(DatabaseInterface $db): void;
}
