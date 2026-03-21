<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class HttpException
 *
 * Thrown by controllers to abort the current request with a specific HTTP status
 * code. The DefaultExceptionHandler converts this into the matching HTTP response.
 *
 * Use Controller::abort() rather than throwing this directly.
 *
 * @package EzPhp\Exceptions
 */
class HttpException extends EzPhpException
{
    /**
     * HttpException Constructor
     *
     * @param int    $statusCode HTTP status code (e.g. 403, 404, 422).
     * @param string $message    Optional human-readable message.
     */
    public function __construct(
        private readonly int $statusCode,
        string $message = ''
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Return the HTTP status code carried by this exception.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
