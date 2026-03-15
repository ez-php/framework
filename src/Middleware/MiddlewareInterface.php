<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Contracts\MiddlewareInterface as ContractsMiddlewareInterface;

/**
 * Interface MiddlewareInterface
 *
 * Framework middleware contract. Extends the contracts interface for backward
 * compatibility — existing middleware implementing this interface also satisfies
 * EzPhp\Contracts\MiddlewareInterface.
 *
 * @package EzPhp\Middleware
 */
interface MiddlewareInterface extends ContractsMiddlewareInterface
{
}
