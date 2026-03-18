<?php

declare(strict_types=1);

namespace Tests\Controller;

use EzPhp\Controller\Controller;
use EzPhp\Exceptions\HttpException;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ControllerTest
 *
 * @package Tests\Controller
 */
#[CoversClass(Controller::class)]
#[UsesClass(Response::class)]
#[UsesClass(ResponseFactory::class)]
#[UsesClass(HttpException::class)]
final class ControllerTest extends TestCase
{
    private ConcreteController $controller;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->controller = new ConcreteController();
    }

    // ── json ─────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_json_returns_json_response(): void
    {
        $response = $this->controller->callJson(['id' => 1]);
        $this->assertSame('{"id":1}', $response->body());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_json_accepts_custom_status(): void
    {
        $response = $this->controller->callJson(['error' => 'bad'], 400);
        $this->assertSame(400, $response->status());
    }

    // ── html ─────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_html_returns_html_response(): void
    {
        $response = $this->controller->callHtml('<h1>Hello</h1>');
        $this->assertSame('<h1>Hello</h1>', $response->body());
        $this->assertSame('text/html; charset=UTF-8', $response->headers()['Content-Type']);
        $this->assertSame(200, $response->status());
    }

    /**
     * @return void
     */
    public function test_html_accepts_custom_status(): void
    {
        $response = $this->controller->callHtml('Error', 500);
        $this->assertSame(500, $response->status());
    }

    // ── redirect ─────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_redirect_returns_redirect_response(): void
    {
        $response = $this->controller->callRedirect('/dashboard');
        $this->assertSame(302, $response->status());
        $this->assertSame('/dashboard', $response->headers()['Location']);
    }

    /**
     * @return void
     */
    public function test_redirect_accepts_custom_status(): void
    {
        $response = $this->controller->callRedirect('/new-url', 301);
        $this->assertSame(301, $response->status());
    }

    // ── abort ─────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_abort_throws_http_exception(): void
    {
        $this->expectException(HttpException::class);
        $this->controller->callAbort(403);
    }

    /**
     * @return void
     */
    public function test_abort_sets_correct_status_code_and_message(): void
    {
        $caught = null;
        try {
            $this->controller->callAbort(422, 'Unprocessable');
        } catch (HttpException $e) {
            $caught = $e;
        }
        $this->assertInstanceOf(HttpException::class, $caught);
        $this->assertSame(422, $caught->getStatusCode());
        $this->assertSame('Unprocessable', $caught->getMessage());
    }
}

/**
 * Class ConcreteController
 *
 * Test stub that exposes the protected Controller helpers as public methods.
 *
 * @package Tests\Controller
 */
class ConcreteController extends Controller
{
    /**
     * @param mixed $data
     * @param int   $status
     *
     * @return Response
     */
    public function callJson(mixed $data, int $status = 200): Response
    {
        return $this->json($data, $status);
    }

    /**
     * @param string $body
     * @param int    $status
     *
     * @return Response
     */
    public function callHtml(string $body, int $status = 200): Response
    {
        return $this->html($body, $status);
    }

    /**
     * @param string $url
     * @param int    $status
     *
     * @return Response
     */
    public function callRedirect(string $url, int $status = 302): Response
    {
        return $this->redirect($url, $status);
    }

    /**
     * @param int    $status
     * @param string $message
     *
     * @return never
     */
    public function callAbort(int $status, string $message = ''): never
    {
        $this->abort($status, $message);
    }
}
