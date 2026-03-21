<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class ForbiddenException
 *
 * Thrown to abort the current request with a 403 Forbidden response.
 *
 * @package EzPhp\Exceptions
 */
final class ForbiddenException extends HttpException
{
    /**
     * ForbiddenException Constructor
     *
     * @param string $message Optional human-readable message. Defaults to "Forbidden".
     */
    public function __construct(string $message = '')
    {
        parent::__construct(403, $message ?: 'Forbidden');
    }
}
