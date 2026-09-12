<?php

declare(strict_types=1);

namespace Tests\Application;

use EzPhp\Application\Application;
use EzPhp\Contracts\ExceptionHandlerInterface;
use EzPhp\Http\HeaderSenderInterface;
use EzPhp\Http\OutputInterface;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseEmitter;
use EzPhp\Http\ResponseInterface;
use EzPhp\Http\StreamedResponse;
use EzPhp\Middleware\TerminableMiddleware;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Class ApplicationSendTest
 *
 * Pins the 2.0 lifecycle: handle() is pure, send() emits and then terminates —
 * after the last chunk, after a stream failure, and after a client disconnect.
 *
 * @package Tests\Application
 */
#[CoversClass(Application::class)]
final class ApplicationSendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SendLog::$entries = [];
    }

    /**
     * @param string                       $path
     * @param \Closure(): iterable<string> $chunks
     *
     * @return Application
     */
    private function appWithStream(string $path, \Closure $chunks): Application
    {
        $app = new Application();
        $app->middleware(LoggingTerminableMiddleware::class);
        $app->bootstrap();
        $app->instance(ExceptionHandlerInterface::class, new LoggingReportHandler());
        $app->make(Router::class)->get($path, fn (Request $r): StreamedResponse => new StreamedResponse($chunks));

        return $app;
    }

    /**
     * @return void
     */
    public function test_handle_does_not_terminate(): void
    {
        $app = $this->appWithStream('/send-pure', fn (): iterable => ['a']);

        $app->handle(new Request('GET', '/send-pure'));

        $this->assertNotContains('terminate', SendLog::$entries);
    }

    /**
     * @return void
     */
    public function test_terminate_runs_after_the_last_chunk(): void
    {
        $app = $this->appWithStream('/send-order', function (): \Generator {
            yield 'a';
            yield 'b';
        });
        $request = new Request('GET', '/send-order');

        $app->send($request, $app->handle($request), $this->emitter());

        $this->assertSame(['write:a', 'write:b', 'terminate'], SendLog::$entries);
    }

    /**
     * @return void
     */
    public function test_stream_failure_is_reported_once_and_terminate_still_runs(): void
    {
        $app = $this->appWithStream('/send-fail', function (): \Generator {
            yield 'a';
            throw new RuntimeException('boom');
        });
        $request = new Request('GET', '/send-fail');

        $app->send($request, $app->handle($request), $this->emitter());

        $this->assertSame(['write:a', 'report:boom', 'terminate'], SendLog::$entries);
    }

    /**
     * @return void
     */
    public function test_client_disconnect_is_not_reported_and_terminate_still_runs(): void
    {
        $app = $this->appWithStream('/send-disconnect', fn (): iterable => ['a', 'b', 'c']);
        $request = new Request('GET', '/send-disconnect');

        $app->send($request, $app->handle($request), $this->emitter(disconnectAfter: 1));

        $this->assertSame(['write:a', 'terminate'], SendLog::$entries);
    }

    /**
     * @return void
     */
    public function test_an_exception_from_report_does_not_prevent_terminate(): void
    {
        $app = $this->appWithStream('/send-report-throws', function (): \Generator {
            yield 'a';
            throw new RuntimeException('boom');
        });
        $app->instance(ExceptionHandlerInterface::class, new ThrowingReportHandler());
        $request = new Request('GET', '/send-report-throws');

        $app->send($request, $app->handle($request), $this->emitter());

        $this->assertSame(['write:a', 'terminate'], SendLog::$entries);
    }

    /**
     * @return void
     */
    public function test_string_response_is_sent_then_terminated(): void
    {
        $app = new Application();
        $app->middleware(LoggingTerminableMiddleware::class);
        $app->bootstrap();
        $app->make(Router::class)->get('/send-string', fn (Request $r): Response => new Response('hello'));
        $request = new Request('GET', '/send-string');

        $app->send($request, $app->handle($request), $this->emitter());

        $this->assertSame(['write:hello', 'terminate'], SendLog::$entries);
    }

    /**
     * @param int|null $disconnectAfter
     *
     * @return ResponseEmitter
     */
    private function emitter(?int $disconnectAfter = null): ResponseEmitter
    {
        return new ResponseEmitter(new NullHeaderSender(), new LoggingOutput($disconnectAfter));
    }
}

/**
 * Shared, ordered event log for this test file.
 */
final class SendLog
{
    /** @var list<string> */
    public static array $entries = [];
}

/**
 * Class LoggingTerminableMiddleware
 */
final class LoggingTerminableMiddleware implements TerminableMiddleware
{
    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        /** @var ResponseInterface */
        return $next($request);
    }

    /**
     * @param Request           $request
     * @param ResponseInterface $response
     *
     * @return void
     */
    public function terminate(Request $request, ResponseInterface $response): void
    {
        SendLog::$entries[] = 'terminate';
    }
}

/**
 * Class LoggingReportHandler
 */
final class LoggingReportHandler implements ExceptionHandlerInterface
{
    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return void
     */
    public function report(Throwable $e, RequestInterface $request): void
    {
        SendLog::$entries[] = 'report:' . $e->getMessage();
    }

    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     */
    public function render(Throwable $e, RequestInterface $request): ResponseInterface
    {
        SendLog::$entries[] = 'render';

        return new Response('error', 500);
    }
}

/**
 * Class ThrowingReportHandler
 */
final class ThrowingReportHandler implements ExceptionHandlerInterface
{
    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return void
     */
    public function report(Throwable $e, RequestInterface $request): void
    {
        throw new RuntimeException('logger is down');
    }

    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     */
    public function render(Throwable $e, RequestInterface $request): ResponseInterface
    {
        return new Response('error', 500);
    }
}

/**
 * Class NullHeaderSender
 */
final class NullHeaderSender implements HeaderSenderInterface
{
    /**
     * @param int $code
     *
     * @return void
     */
    public function sendStatus(int $code): void
    {
    }

    /**
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    public function sendHeader(string $name, string $value): void
    {
    }

    /**
     * @param string $headerValue
     *
     * @return void
     */
    public function sendCookie(string $headerValue): void
    {
    }
}

/**
 * Class LoggingOutput
 */
final class LoggingOutput implements OutputInterface
{
    private int $writes = 0;

    /**
     * @param int|null $disconnectAfter
     */
    public function __construct(private readonly ?int $disconnectAfter = null)
    {
    }

    /**
     * @param string $chunk
     *
     * @return void
     */
    public function write(string $chunk): void
    {
        $this->writes++;
        SendLog::$entries[] = 'write:' . $chunk;
    }

    /**
     * @return bool
     */
    public function isClientConnected(): bool
    {
        return $this->disconnectAfter === null || $this->writes < $this->disconnectAfter;
    }

    /**
     * @return void
     */
    public function ignoreUserAbort(): void
    {
    }
}
