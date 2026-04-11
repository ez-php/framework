<?php

declare(strict_types=1);

namespace Tests\Controller;

use EzPhp\Controller\ApiController;
use EzPhp\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class ApiControllerTest
 *
 * @package Tests\Controller
 */
#[CoversClass(ApiController::class)]
final class ApiControllerTest extends TestCase
{
    private ConcreteApiController $controller;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->controller = new ConcreteApiController();
    }

    // ── success ──────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_success_returns_200_with_data_envelope(): void
    {
        $response = $this->controller->callSuccess(['id' => 1, 'name' => 'Alice']);

        $this->assertSame(200, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertSame('{"data":{"id":1,"name":"Alice"}}', $response->body());
    }

    /**
     * @return void
     */
    public function test_success_accepts_custom_status(): void
    {
        $response = $this->controller->callSuccess(['ok' => true], 202);

        $this->assertSame(202, $response->status());
    }

    // ── created ──────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_created_returns_201_with_data_envelope(): void
    {
        $response = $this->controller->callCreated(['id' => 42]);

        $this->assertSame(201, $response->status());
        $this->assertSame('{"data":{"id":42}}', $response->body());
    }

    // ── error ─────────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_error_returns_correct_status_and_envelope(): void
    {
        $response = $this->controller->callError('INSUFFICIENT_GOLD', 'Not enough gold', 422);

        $this->assertSame(422, $response->status());
        $this->assertSame(
            '{"error":{"code":"INSUFFICIENT_GOLD","message":"Not enough gold"}}',
            $response->body(),
        );
    }

    // ── validationError ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_validation_error_returns_422_with_error_envelope(): void
    {
        $response = $this->controller->callValidationError(['email' => ['The email field is required.']]);

        $this->assertSame(422, $response->status());

        /** @var array{error: array{code: string, errors: array<string, list<string>>}} $body */
        $body = json_decode($response->body(), true);
        $this->assertSame('VALIDATION_FAILED', $body['error']['code']);
        $this->assertArrayHasKey('email', $body['error']['errors']);
    }

    // ── unauthorized ─────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_unauthorized_returns_401_with_error_envelope(): void
    {
        $response = $this->controller->callUnauthorized();

        $this->assertSame(401, $response->status());
        $this->assertSame(
            '{"error":{"code":"UNAUTHORIZED","message":"Unauthorized"}}',
            $response->body(),
        );
    }
}

/**
 * Class ConcreteApiController
 *
 * Test stub that exposes protected ApiController helpers as public methods.
 *
 * @package Tests\Controller
 */
class ConcreteApiController extends ApiController
{
    /**
     * @param mixed $data
     * @param int   $status
     *
     * @return Response
     */
    public function callSuccess(mixed $data, int $status = 200): Response
    {
        return $this->success($data, $status);
    }

    /**
     * @param mixed $data
     *
     * @return Response
     */
    public function callCreated(mixed $data): Response
    {
        return $this->created($data);
    }

    /**
     * @param string $code
     * @param string $message
     * @param int    $status
     *
     * @return Response
     */
    public function callError(string $code, string $message, int $status): Response
    {
        return $this->error($code, $message, $status);
    }

    /**
     * @param array<string, list<string>> $errors
     *
     * @return Response
     */
    public function callValidationError(array $errors): Response
    {
        return $this->validationError($errors);
    }

    /**
     * @return Response
     */
    public function callUnauthorized(): Response
    {
        return $this->unauthorized();
    }
}
