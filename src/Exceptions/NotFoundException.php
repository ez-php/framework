<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class NotFoundException
 *
 * Thrown to abort the current request with a 404 Not Found response.
 *
 * @package EzPhp\Exceptions
 */
final class NotFoundException extends HttpException
{
    /**
     * NotFoundException Constructor
     *
     * @param string $message Optional human-readable message. Defaults to "Not Found".
     */
    public function __construct(string $message = '')
    {
        parent::__construct(404, $message ?: 'Not Found');
    }
}
