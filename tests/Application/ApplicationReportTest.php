<?php

declare(strict_types=1);

namespace Tests\Application;

use EzPhp\Application\Application;
use EzPhp\Contracts\ExceptionHandlerInterface;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use EzPhp\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;
use Throwable;

/**
 * Class ApplicationReportTest
 *
 * @package Tests\Application
 */
#[CoversClass(Application::class)]
final class ApplicationReportTest extends TestCase
{
    /**
     * @return void
     */
    public function test_handle_reports_then_renders_a_route_exception_once_each(): void
    {
        $handler = new OrderRecordingExceptionHandler();

        $app = new Application();
        $app->bootstrap();
        // instance(), not bind(): it replaces an already-cached singleton too.
        $app->instance(ExceptionHandlerInterface::class, $handler);
        $app->make(Router::class)->get('/report-order', function (Request $r): Response {
            throw new RuntimeException('boom');
        });

        $response = $app->handle(new Request('GET', '/report-order'));

        $this->assertSame(500, $response->status());
        $this->assertSame(['report', 'render'], $handler->calls);
    }
}

/**
 * Class OrderRecordingExceptionHandler
 *
 * @package Tests\Application
 */
final class OrderRecordingExceptionHandler implements ExceptionHandlerInterface
{
    /** @var list<string> */
    public array $calls = [];

    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return void
     */
    public function report(Throwable $e, RequestInterface $request): void
    {
        $this->calls[] = 'report';
    }

    /**
     * @param Throwable        $e
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     */
    public function render(Throwable $e, RequestInterface $request): ResponseInterface
    {
        $this->calls[] = 'render';

        return new Response('error', 500);
    }
}
