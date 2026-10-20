<?php

declare(strict_types=1);

namespace EzPhp\Controller;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\GateInterface;
use EzPhp\Contracts\TranslatorInterface;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ForbiddenException;
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
     * The database rules (`unique`, `exists`) need $db and throw a RuntimeException
     * without it; messages are localised only when $translator is given. Inject both
     * into the controller's constructor and pass them through, e.g.
     * `$this->validate($request, $rules, $this->db, $this->translator)`.
     *
     * @param Request                                                          $request    The current HTTP request.
     * @param array<string, string|list<string|RuleInterface|ConditionalRule>> $rules      Validation rules keyed by field name.
     * @param DatabaseInterface|null                                           $db         For the `unique`/`exists` rules.
     * @param TranslatorInterface|null                                         $translator For localised error messages.
     *
     * @return array<string, mixed>
     * @throws ValidationException When validation fails.
     */
    protected function validate(
        Request $request,
        array $rules,
        ?DatabaseInterface $db = null,
        ?TranslatorInterface $translator = null,
    ): array {
        Validator::make($request->all(), $rules, $db, $translator)->validate();

        return $request->all();
    }

    /**
     * Authorize the current user for an ability, or abort with 403.
     *
     * The gate is passed in like validate()'s database — no service locator in
     * the base class. Inject it into the controller's constructor as an
     * optional dependency, `private ?GateInterface $gate = null`: the container
     * fills it when ez-php/authorization (or any GateInterface binding) is
     * installed and leaves null otherwise, which this method reports clearly
     * instead of silently allowing.
     *
     * @param GateInterface|null $gate    The gate, usually the controller's injected one.
     * @param string             $ability Ability name, e.g. 'update'.
     * @param mixed              $subject Optional subject (object or class-string).
     *
     * @return void
     * @throws ForbiddenException   When the ability is denied (403).
     * @throws ApplicationException When no gate is available.
     */
    protected function authorize(?GateInterface $gate, string $ability, mixed $subject = null): void
    {
        if ($gate === null) {
            throw new ApplicationException(
                "Cannot authorize '{$ability}': no " . GateInterface::class . ' is bound. '
                . 'Register ez-php/authorization\'s AuthorizationServiceProvider (or bind your own GateInterface).'
            );
        }

        if (!$gate->allows($ability, $subject)) {
            throw new ForbiddenException("This action is unauthorized: {$ability}.");
        }
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
