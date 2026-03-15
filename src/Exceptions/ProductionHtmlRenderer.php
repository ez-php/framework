<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

/**
 * Class ProductionHtmlRenderer
 *
 * Renders clean production error pages (404, 500, etc.).
 * Looks for a custom template at {templatePath}/{status}.php first;
 * falls back to a built-in minimal HTML page.
 *
 * Custom templates receive a single variable: int $status.
 *
 * @package EzPhp\Exceptions
 */
final class ProductionHtmlRenderer
{
    /**
     * @param string $templatePath Directory to look for custom error templates (e.g. resources/errors).
     */
    public function __construct(private readonly string $templatePath = '')
    {
    }

    /**
     * @param int $status HTTP status code
     *
     * @return string
     */
    public function render(int $status): string
    {
        $template = $this->resolveTemplate($status);

        if ($template !== null) {
            return $this->renderTemplate($template, $status);
        }

        return $this->renderBuiltIn($status);
    }

    /**
     * @param int $status
     *
     * @return string|null
     */
    private function resolveTemplate(int $status): ?string
    {
        if ($this->templatePath === '') {
            return null;
        }

        $path = rtrim($this->templatePath, '/') . '/' . $status . '.php';

        return is_file($path) ? $path : null;
    }

    /**
     * @param string $path
     * @param int    $status
     *
     * @return string
     */
    private function renderTemplate(string $path, int $status): string
    {
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    /**
     * @param int $status
     *
     * @return string
     */
    private function renderBuiltIn(int $status): string
    {
        $title = $this->titleFor($status);
        $message = $this->messageFor($status);

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>{$status} {$title}</title>
                <style>
                    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: ui-sans-serif, system-ui, sans-serif; background: #f8fafc;
                           color: #1e293b; display: flex; align-items: center; justify-content: center;
                           min-height: 100vh; }
                    .box { text-align: center; padding: 48px; }
                    .code { font-size: 80px; font-weight: 800; color: #e2e8f0; line-height: 1; }
                    .title { font-size: 24px; font-weight: 600; margin: 8px 0 12px; }
                    .message { font-size: 15px; color: #64748b; }
                </style>
            </head>
            <body>
                <div class="box">
                    <div class="code">{$status}</div>
                    <div class="title">{$title}</div>
                    <p class="message">{$message}</p>
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * @param int $status
     *
     * @return string
     */
    private function titleFor(int $status): string
    {
        return match ($status) {
            404 => 'Not Found',
            500 => 'Internal Server Error',
            403 => 'Forbidden',
            401 => 'Unauthorized',
            default => 'Error',
        };
    }

    /**
     * @param int $status
     *
     * @return string
     */
    private function messageFor(int $status): string
    {
        return match ($status) {
            404 => 'The page you are looking for could not be found.',
            500 => 'Something went wrong. Please try again later.',
            403 => 'You do not have permission to access this resource.',
            401 => 'Authentication is required to access this resource.',
            default => 'An unexpected error occurred.',
        };
    }
}
