<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Http\RequestInterface;
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
     * @var list<array{0: class-string, 1: callable(Throwable, RequestInterface): Response}>
     */
    private array $renderables = [];

    /**
     * @var list<array{0: class-string, 1: int, 2: string}>
     */
    private array $mappings = [];

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
        if ($this->debug && $this->isProductionEnvironment()) {
            error_log(
                '[ez-php] WARNING: APP_DEBUG is enabled in a production environment. '
                . 'Full stack traces and query details will be exposed in error responses. '
                . 'Set APP_DEBUG=false for production deployments.'
            );
        }
    }

    /**
     * Return true when the application environment appears to be production.
     *
     * Reads APP_ENV from $_SERVER first (populated by the web server), then
     * $_ENV (populated by putenv / dotenv), with a safe default of false.
     *
     * @return bool
     */
    private function isProductionEnvironment(): bool
    {
        $raw = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? '';
        $env = is_string($raw) ? $raw : '';

        return strtolower($env) === 'production';
    }

    /**
     * Register a custom renderer for a specific exception class.
     * The renderer callable receives the exception and the request, and must return a Response.
     * The first matching renderer is used (checked in registration order).
     *
     * @param class-string                                        $exceptionClass
     * @param callable(Throwable, RequestInterface): Response     $renderer
     *
     * @return $this
     */
    public function renderable(string $exceptionClass, callable $renderer): self
    {
        $this->renderables[] = [$exceptionClass, $renderer];

        return $this;
    }

    /**
     * Register a domain exception → HTTP status mapping.
     *
     * When the given exception class (or a subclass) is thrown and no custom
     * renderable matches it, the handler responds with $status instead of 500.
     * If $code is non-empty it is used as the JSON `code` field instead of the
     * numeric HTTP status.
     *
     * @param class-string $exceptionClass
     * @param int          $status
     * @param string       $code
     *
     * @return $this
     */
    public function map(string $exceptionClass, int $status, string $code = ''): static
    {
        $this->mappings[] = [$exceptionClass, $status, $code];

        return $this;
    }

    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return Response
     */
    public function render(Throwable $e, RequestInterface $request): Response
    {
        // Check custom renderers first (in registration order)
        foreach ($this->renderables as [$class, $renderer]) {
            if ($e instanceof $class) {
                return $renderer($e, $request);
            }
        }

        // Check domain exception mappings
        foreach ($this->mappings as [$class, $mappedStatus, $mappedCode]) {
            if ($e instanceof $class) {
                return $this->buildMappedResponse($e, $request, $mappedStatus, $mappedCode);
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
     * @param Throwable        $e
     * @param RequestInterface $request
     * @param int              $status
     * @param string           $code
     *
     * @return Response
     */
    private function buildMappedResponse(
        Throwable $e,
        RequestInterface $request,
        int $status,
        string $code,
    ): Response {
        /** @var string $accept */
        $accept = $request->header('accept', '');

        if (str_contains($accept, 'application/json')) {
            $jsonCode = $code !== '' ? $code : $status;
            $message = $e->getMessage();
            $envelope = ['error' => ['code' => $jsonCode, 'message' => $message]];
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
