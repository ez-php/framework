<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use Throwable;

/**
 * Class DefaultExceptionHandler
 *
 * @package EzPhp\Exceptions
 */
final class DefaultExceptionHandler implements ExceptionHandler
{
    /**
     * DefaultExceptionHandler Constructor
     *
     * @param bool $debug
     */
    public function __construct(private readonly bool $debug = false)
    {
    }

    /**
     * @param Throwable $e
     * @param Request   $request
     *
     * @return Response
     */
    public function render(Throwable $e, Request $request): Response
    {
        $status = $e instanceof RouteException ? 404 : 500;
        $message = $this->resolveMessage($e, $status);

        /** @var string $accept */
        $accept = $request->header('accept', '');

        if (str_contains($accept, 'application/json')) {
            $json = json_encode(['error' => $message]) ?: '{"error":"Internal Server Error"}';

            return (new Response($json, $status))
                ->withHeader('Content-Type', 'application/json');
        }

        return new Response($message, $status);
    }

    /**
     * @param Throwable $e
     * @param int       $status
     *
     * @return string
     */
    private function resolveMessage(Throwable $e, int $status): string
    {
        if ($status === 500 && !$this->debug) {
            return 'Internal Server Error';
        }

        return $e->getMessage();
    }
}
