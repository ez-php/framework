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
    /**
     * Register a domain exception → HTTP status mapping.
     *
     * When the given exception class (or a subclass) is thrown and no custom
     * renderable matches it, the handler responds with $status instead of 500.
     * If $code is non-empty it is used as the JSON `code` field instead of the
     * numeric HTTP status.
     *
     * @param class-string $exceptionClass
     * @param int          $status
     * @param string       $code           Optional machine-readable error code (e.g. 'INSUFFICIENT_GOLD').
     *
     * @return $this
     */
    public function map(string $exceptionClass, int $status, string $code = ''): static;
}
