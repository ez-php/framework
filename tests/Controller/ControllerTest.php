<?php

declare(strict_types=1);

namespace Tests\Controller;

use EzPhp\Controller\Controller;
use EzPhp\Exceptions\HttpException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Validation\ConditionalRule;
use EzPhp\Validation\RuleInterface;
use EzPhp\Validation\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class ControllerTest
 *
 * @package Tests\Controller
 */
#[CoversClass(Controller::class)]
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

    // ── validate ─────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_validate_returns_all_data_on_success(): void
    {
        $request = new Request(method: 'POST', uri: '/', body: ['name' => 'Alice']);

        $data = $this->controller->callValidate($request, ['name' => 'required|string']);

        $this->assertSame(['name' => 'Alice'], $data);
    }

    /**
     * @return void
     */
    public function test_validate_throws_validation_exception_on_failure(): void
    {
        $request = new Request(method: 'POST', uri: '/');

        $this->expectException(ValidationException::class);

        $this->controller->callValidate($request, ['name' => 'required']);
    }

    /**
     * @return void
     */
    public function test_validate_exception_contains_field_errors(): void
    {
        $request = new Request(method: 'POST', uri: '/');

        try {
            $this->controller->callValidate($request, ['email' => 'required|email']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
        }
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

    /**
     * @param Request                                                          $request
     * @param array<string, string|list<string|RuleInterface|ConditionalRule>> $rules
     *
     * @return array<string, mixed>
     */
    public function callValidate(Request $request, array $rules): array
    {
        return $this->validate($request, $rules);
    }
}
