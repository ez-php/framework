<?php

declare(strict_types=1);

namespace EzPhp\Controller;

use EzPhp\Http\Response;

/**
 * Class ApiController
 *
 * Abstract base class for JSON API controllers.
 * Provides standard response helpers with a consistent JSON envelope:
 *
 *   Success:          {"data": ...}
 *   Error:            {"error": {"code": "...", "message": "..."}}
 *   Validation error: {"error": {"code": "VALIDATION_FAILED", "errors": {...}}}
 *   Unauthorized:     {"error": {"code": "UNAUTHORIZED", "message": "Unauthorized"}}
 *
 * @package EzPhp\Controller
 */
abstract class ApiController extends Controller
{
    /**
     * Return a 200 JSON response with a data envelope.
     *
     * @param mixed $data   Any JSON-serialisable value.
     * @param int   $status HTTP status code (default 200).
     *
     * @return Response
     * @throws \JsonException
     */
    protected function success(mixed $data, int $status = 200): Response
    {
        return $this->json(['data' => $data], $status);
    }

    /**
     * Return a 201 Created JSON response with a data envelope.
     *
     * @param mixed $data Any JSON-serialisable value.
     *
     * @return Response
     * @throws \JsonException
     */
    protected function created(mixed $data): Response
    {
        return $this->json(['data' => $data], 201);
    }

    /**
     * Return a JSON error response.
     *
     * @param string $code    Machine-readable error code (e.g. 'INSUFFICIENT_GOLD').
     * @param string $message Human-readable error description.
     * @param int    $status  HTTP status code.
     *
     * @return Response
     * @throws \JsonException
     */
    protected function error(string $code, string $message, int $status): Response
    {
        return $this->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /**
     * Return a 422 Unprocessable Entity response with field-level validation errors.
     *
     * @param array<string, list<string>> $errors Field → error messages.
     *
     * @return Response
     * @throws \JsonException
     */
    protected function validationError(array $errors): Response
    {
        return $this->json(
            ['error' => ['code' => 'VALIDATION_FAILED', 'errors' => $errors]],
            422,
        );
    }

    /**
     * Return a 401 Unauthorized response.
     *
     * @return Response
     * @throws \JsonException
     */
    protected function unauthorized(): Response
    {
        return $this->json(
            ['error' => ['code' => 'UNAUTHORIZED', 'message' => 'Unauthorized']],
            401,
        );
    }
}
