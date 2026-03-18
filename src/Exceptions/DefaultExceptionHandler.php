<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\I18n\Translator;
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
     * @param bool            $debug        Show full exception details in HTML responses.
     * @param string          $templatePath Directory for custom production error templates (e.g. resources/errors).
     *                                      Templates are loaded as {templatePath}/{status}.php.
     * @param Translator|null $translator   Optional translator for localised production error strings.
     */
    public function __construct(
        private readonly bool $debug = false,
        private readonly string $templatePath = '',
        private readonly ?Translator $translator = null,
    ) {
    }

    /**
     * @param Throwable $e
     * @param Request   $request
     *
     * @return Response
     */
    public function render(Throwable $e, Request $request): Response
    {
        $status = match (true) {
            $e instanceof RouteException => 404,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 500,
        };

        /** @var string $accept */
        $accept = $request->header('accept', '');

        if (str_contains($accept, 'application/json')) {
            $message = $this->resolveMessage($e, $status);
            $json = json_encode(['error' => $message]) ?: '{"error":"Internal Server Error"}';

            return (new Response($json, $status))
                ->withHeader('Content-Type', 'application/json');
        }

        $html = $this->debug
            ? (new DebugHtmlRenderer())->render($e, $request)
            : (new ProductionHtmlRenderer($this->templatePath, $this->translator))->render($status);

        return (new Response($html, $status))
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
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
