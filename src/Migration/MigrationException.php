<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Exceptions\EzPhpException;

/**
 * Class MigrationException
 *
 * Thrown when a migration up() or down() method fails.
 * Wraps the original exception and includes the migration filename for context.
 *
 * @package EzPhp\Migration
 */
final class MigrationException extends EzPhpException
{
}
