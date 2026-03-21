<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class UnauthorizedException
 *
 * Thrown to abort the current request with a 401 Unauthorized response.
 *
 * @package EzPhp\Exceptions
 */
final class UnauthorizedException extends HttpException
{
    /**
     * UnauthorizedException Constructor
     *
     * @param string $message Optional human-readable message. Defaults to "Unauthorized".
     */
    public function __construct(string $message = '')
    {
        parent::__construct(401, $message ?: 'Unauthorized');
    }
}
