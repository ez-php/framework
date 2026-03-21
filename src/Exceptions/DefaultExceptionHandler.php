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
 * Converts exceptions into HTTP responses. Supports:
 * - Custom renderers registered via renderable()
 * - JSON error responses with structured envelope
 * - Debug/production HTML responses
 *
 * @package EzPhp\Exceptions
 */
class DefaultExceptionHandler implements ExceptionHandler
{
    /**
     * @var list<array{0: class-string, 1: callable(Throwable, Request): Response}>
     */
    private array $renderables = [];

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
     * Register a custom renderer for a specific exception class.
     * The renderer callable receives the exception and the request, and must return a Response.
     * The first matching renderer is used (checked in registration order).
     *
     * @param class-string                                $exceptionClass
     * @param callable(Throwable, Request): Response      $renderer
     *
     * @return $this
     */
    public function renderable(string $exceptionClass, callable $renderer): self
    {
        $this->renderables[] = [$exceptionClass, $renderer];

        return $this;
    }

    /**
     * @param Throwable $e
     * @param Request   $request
     *
     * @return Response
     */
    public function render(Throwable $e, Request $request): Response
    {
        // Check custom renderers first (in registration order)
        foreach ($this->renderables as [$class, $renderer]) {
            if ($e instanceof $class) {
                return $renderer($e, $request);
            }
        }

        $status = match (true) {
            $e instanceof RouteException => 404,
            $e instanceof HttpException => $e->getStatusCode(),
            default => 500,
        };

        /** @var string $accept */
        $accept = $request->header('accept', '');

        if (str_contains($accept, 'application/json')) {
            $message = $this->resolveMessage($e, $status);
            $envelope = ['error' => ['code' => $status, 'message' => $message]];
            $json = json_encode($envelope) ?: '{"error":{"code":500,"message":"Internal Server Error"}}';

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
