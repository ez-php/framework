<?php

declare(strict_types=1);

namespace EzPhp\Controller;

use EzPhp\Exceptions\HttpException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseFactory;
use EzPhp\Validation\ConditionalRule;
use EzPhp\Validation\RuleInterface;
use EzPhp\Validation\ValidationException;
use EzPhp\Validation\Validator;

/**
 * Class Controller
 *
 * Optional abstract base class for application controllers.
 * Provides response-building helpers (json, html, redirect) and abort().
 *
 * Extend this class to avoid coupling your controllers to ResponseFactory directly.
 * All helpers delegate to ResponseFactory — no state is held here.
 *
 * @package EzPhp\Controller
 */
abstract class Controller
{
    /**
     * Create a JSON response.
     *
     * @param mixed $data   Any JSON-serialisable value.
     * @param int   $status HTTP status code (default 200).
     *
     * @return Response
     * @throws \JsonException
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return ResponseFactory::json($data, $status);
    }

    /**
     * Create an HTML response.
     *
     * @param string $body   HTML content.
     * @param int    $status HTTP status code (default 200).
     *
     * @return Response
     */
    protected function html(string $body, int $status = 200): Response
    {
        return ResponseFactory::html($body, $status);
    }

    /**
     * Create a redirect response.
     *
     * @param string $url    Target URL.
     * @param int    $status HTTP redirect status code (default 302).
     *
     * @return Response
     */
    protected function redirect(string $url, int $status = 302): Response
    {
        return ResponseFactory::redirect($url, $status);
    }

    /**
     * Validate the given request data against the given rules.
     *
     * Runs $request->all() through the Validator and throws a ValidationException
     * on failure, which the DefaultExceptionHandler converts to a 422 response.
     * Returns the merged query + body array on success so callers can use the
     * validated data directly.
     *
     * Requires ez-php/validation to be installed.
     *
     * @param Request                                                          $request The current HTTP request.
     * @param array<string, string|list<string|RuleInterface|ConditionalRule>> $rules   Validation rules keyed by field name.
     *
     * @return array<string, mixed>
     * @throws ValidationException When validation fails.
     */
    protected function validate(Request $request, array $rules): array
    {
        Validator::make($request->all(), $rules)->validate();

        return $request->all();
    }

    /**
     * Abort the current request by throwing an HttpException.
     *
     * The DefaultExceptionHandler converts the exception into an HTTP response
     * with the given status code.
     *
     * @param int    $status  HTTP status code (e.g. 403, 404, 422).
     * @param string $message Optional error message shown in debug mode.
     *
     * @return never
     * @throws HttpException
     */
    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}
