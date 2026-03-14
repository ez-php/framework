<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\Response;

/**
 * Interface TerminableMiddleware
 *
 * @package EzPhp\Middleware
 */
interface TerminableMiddleware extends MiddlewareInterface
{
    /**
     * Called after the response has been sent to the client.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return void
     */
    public function terminate(Request $request, Response $response): void;
}
