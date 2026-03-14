<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use Throwable;

/**
 * Interface ExceptionHandler
 *
 * @package EzPhp\Exceptions
 */
interface ExceptionHandler
{
    /**
     * @param Throwable $e
     * @param Request   $request
     *
     * @return Response
     */
    public function render(Throwable $e, Request $request): Response;
}
