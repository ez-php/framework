<?php

declare(strict_types=1);

namespace EzPhp\Middleware;

use EzPhp\Http\Request;
use EzPhp\Http\ResponseInterface;

/**
 * Interface TerminableMiddleware
 *
 * @package EzPhp\Middleware
 */
interface TerminableMiddleware extends MiddlewareInterface
{
    /**
     * Called after the response has been sent to the client — after the last
     * chunk of a streamed response, and also after a stream failure or a client
     * disconnect.
     *
     * @param Request           $request
     * @param ResponseInterface $response
     *
     * @return void
     */
    public function terminate(Request $request, ResponseInterface $response): void;
}
