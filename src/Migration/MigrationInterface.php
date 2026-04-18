<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Contracts\Schema\SchemaInterface;

/**
 * Interface MigrationInterface
 *
 * @package EzPhp\Migration
 */
interface MigrationInterface
{
    /**
     * @param SchemaInterface $schema
     *
     * @return void
     */
    public function up(SchemaInterface $schema): void;

    /**
     * @param SchemaInterface $schema
     *
     * @return void
     */
    public function down(SchemaInterface $schema): void;
}
