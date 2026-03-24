<?php

declare(strict_types=1);

namespace EzPhp\Exceptions;

use EzPhp\Http\Request;
use Throwable;

/**
 * Class DebugHtmlRenderer
 *
 * Renders a self-contained HTML debug page for exceptions in development mode.
 * Shows exception class, message, file/line, full stack trace, and request context.
 *
 * @internal
 * @package EzPhp\Exceptions
 */
final class DebugHtmlRenderer
{
    /**
     * @param Throwable $e
     * @param Request   $request
     *
     * @return string
     */
    public function render(Throwable $e, Request $request): string
    {
        $class = get_class($e);
        $message = $this->esc($e->getMessage());
        $file = $this->esc($e->getFile());
        $line = $e->getLine();
        $trace = $this->renderTrace($e);
        $method = $this->esc($request->method());
        $uri = $this->esc($request->uri());

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Exception: {$class}</title>
                <style>
                    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: ui-monospace, 'Cascadia Code', 'Source Code Pro', monospace;
                           font-size: 14px; background: #0f1117; color: #e2e8f0; min-height: 100vh; }
                    header { background: #1e1e2e; border-bottom: 3px solid #f43f5e; padding: 28px 36px; }
                    header h1 { font-size: 20px; font-weight: 700; color: #f43f5e; margin-bottom: 10px; }
                    header .message { font-size: 16px; color: #fcd34d; word-break: break-word; margin-bottom: 8px; }
                    header .location { font-size: 12px; color: #64748b; }
                    header .location span { color: #94a3b8; }
                    section { padding: 24px 36px; border-bottom: 1px solid #1e293b; }
                    section h2 { font-size: 11px; font-weight: 600; letter-spacing: .1em;
                                 text-transform: uppercase; color: #475569; margin-bottom: 14px; }
                    .request-line { font-size: 15px; }
                    .request-line .method { color: #34d399; font-weight: 700; margin-right: 10px; }
                    .request-line .uri { color: #93c5fd; }
                    .trace ol { list-style: none; counter-reset: frame; }
                    .trace li { counter-increment: frame; display: flex; gap: 16px;
                                padding: 10px 0; border-bottom: 1px solid #1e293b; }
                    .trace li:last-child { border-bottom: none; }
                    .trace li::before { content: counter(frame); min-width: 28px; text-align: right;
                                        color: #334155; font-size: 12px; padding-top: 2px; flex-shrink: 0; }
                    .trace .call { color: #c4b5fd; word-break: break-all; }
                    .trace .call .class-name { color: #e2e8f0; }
                    .trace .call .type { color: #475569; }
                    .trace .call .fn { color: #7dd3fc; }
                    .trace .loc { font-size: 12px; color: #475569; margin-top: 4px; }
                    .trace .loc span { color: #64748b; }
                    .frame-info { flex: 1; min-width: 0; }
                    .internal { color: #475569; font-style: italic; }
                </style>
            </head>
            <body>
                <header>
                    <h1>{$class}</h1>
                    <p class="message">{$message}</p>
                    <p class="location">in <span>{$file}</span> on line <span>{$line}</span></p>
                </header>
                <section>
                    <h2>Request</h2>
                    <p class="request-line">
                        <span class="method">{$method}</span><span class="uri">{$uri}</span>
                    </p>
                </section>
                <section class="trace">
                    <h2>Stack Trace</h2>
                    <ol>
                        {$trace}
                    </ol>
                </section>
            </body>
            </html>
            HTML;
    }

    /**
     * @param Throwable $e
     *
     * @return string
     */
    private function renderTrace(Throwable $e): string
    {
        $frames = $e->getTrace();
        $html = '';

        foreach ($frames as $frame) {
            $call = $this->renderCall($frame);
            $loc = $this->renderLocation($frame);

            $html .= "<li><div class=\"frame-info\"><div class=\"call\">{$call}</div>{$loc}</div></li>\n";
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return string
     */
    private function renderCall(array $frame): string
    {
        $fn = is_string($frame['function'] ?? null) ? $this->esc($frame['function']) : '{closure}';

        if (is_string($frame['class'] ?? null)) {
            $class = $this->esc($frame['class']);
            $type = is_string($frame['type'] ?? null) ? $this->esc($frame['type']) : '-&gt;';

            return "<span class=\"class-name\">{$class}</span><span class=\"type\">{$type}</span><span class=\"fn\">{$fn}()</span>";
        }

        return "<span class=\"fn\">{$fn}()</span>";
    }

    /**
     * @param array<string, mixed> $frame
     *
     * @return string
     */
    private function renderLocation(array $frame): string
    {
        if (!is_string($frame['file'] ?? null)) {
            return '<div class="loc"><span class="internal">[internal]</span></div>';
        }

        $file = $this->esc($frame['file']);
        $line = is_int($frame['line'] ?? null) ? $frame['line'] : 0;

        return "<div class=\"loc\">{$file}<span>:{$line}</span></div>";
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
