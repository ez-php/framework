<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;

/**
 * Interface MiddlewareInterface
 *
 * @package EzPhp\Middleware
 */
interface MiddlewareInterface
{
    /**
     * @param Request  $request
     * @param callable $next
     *
     * @return Response
     */
    public function handle(Request $request, callable $next): Response;
}
