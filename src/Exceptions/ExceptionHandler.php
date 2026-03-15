<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Contracts\ExceptionHandlerInterface;

/**
 * Interface ExceptionHandler
 *
 * Framework exception handler contract. Extends the contracts interface
 * so that implementations satisfy both the framework type and the contracts type.
 *
 * @package EzPhp\Exceptions
 */
interface ExceptionHandler extends ExceptionHandlerInterface
{
}
