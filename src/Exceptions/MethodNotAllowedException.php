<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class MethodNotAllowedException
 *
 * Thrown by the Router when the request path matches a route, but only for
 * other HTTP methods. Rendered as 405 Method Not Allowed with an `Allow`
 * header listing the methods that do match (RFC 9110 §15.5.6).
 *
 * @package EzPhp\Exceptions
 */
final class MethodNotAllowedException extends HttpException
{
    /**
     * MethodNotAllowedException Constructor
     *
     * @param list<string> $allowedMethods Methods the path is registered for, e.g. ['GET', 'HEAD'].
     */
    public function __construct(private readonly array $allowedMethods)
    {
        parent::__construct(405, 'Method Not Allowed');
    }

    /**
     * The methods to list in the `Allow` response header.
     *
     * @return list<string>
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
