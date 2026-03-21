<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class BadRequestException
 *
 * Thrown to abort the current request with a 400 Bad Request response.
 *
 * @package EzPhp\Exceptions
 */
final class BadRequestException extends HttpException
{
    /**
     * BadRequestException Constructor
     *
     * @param string $message Optional human-readable message. Defaults to "Bad Request".
     */
    public function __construct(string $message = '')
    {
        parent::__construct(400, $message ?: 'Bad Request');
    }
}
