<?php

declare(strict_types=1);

namespace EzPhp\Application;

use EzPhp\Container\Container;
use EzPhp\Contracts\CommandRegistryInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ExceptionHandlerInterface;
use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseEmitter;
use EzPhp\Http\ResponseInterface;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Routing\Router;
use ReflectionException;
use Throwable;

/**
 * Class Application
 *
 * @package EzPhp\Application
 */
final class Application implements ContainerInterface, CommandRegistryInterface
{
    private bool $booted = false;

    private Container $container;

    /**
     * @var list<\EzPhp\Contracts\ServiceProvider>
     */
    private array $serviceProviders = [];

    /**
     * Deferred provider binding map: class-string → provider class-string.
     * Populated during bootstrap; provider is loaded on first make() for that class.
     *
     * @var array<string, class-string<\EzPhp\Contracts\ServiceProvider>>
     */
    private array $deferredBindings = [];

    /**
     * Already-loaded deferred provider class-strings (to avoid double-loading).
     *
     * @var array<class-string<\EzPhp\Contracts\ServiceProvider>, true>
     */
    private array $loadedDeferredProviders = [];

    /**
     * True while a deferred provider's own register()/boot() is running, so a
     * nested activateDeferredProviderFor() call (triggered by that provider
     * resolving another deferred binding) knows to queue rather than boot
     * immediately. See $deferredBootQueue.
     */
    private bool $activatingDeferredProvider = false;

    /**
     * Deferred providers whose register() has run but whose boot() was
     * queued because it was triggered while another deferred provider's
     * own register()/boot() was still running (see activateDeferredProviderFor()).
     * Drained, in order, right after the outer provider's boot() completes.
     *
     * @var list<\EzPhp\Contracts\ServiceProvider>
     */
    private array $deferredBootQueue = [];

    /**
     * @var list<class-string<\EzPhp\Contracts\ServiceProvider>>
     */
    private array $userProviders = [];

    /**
     * @var list<class-string<MiddlewareInterface>>
     */
    private array $globalMiddleware = [];

    /**
     * @var array<string, class-string<MiddlewareInterface>>
     */
    private array $middlewareAliases = [];

    /**
     * @var array<string, list<class-string<MiddlewareInterface>>>
     */
    private array $middlewareGroups = [];

    /**
     * @var list<class-string>
     */
    private array $userCommands = [];

    private bool $middlewarePushed = false;

    /**
     * Application Constructor
     *
     * @param string $basePath Absolute path to the project root. Defaults to the
     *                         directory two levels above this file (i.e. the
     *                         project root when installed in src/Application/).
     */
    public function __construct(private readonly string $basePath = '')
    {
    }

    /**
     * Return an absolute path relative to the project root.
     *
     * @param string $path Optional sub-path to append (e.g. 'config', 'database/migrations').
     *
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        $base = $this->basePath !== '' ? $this->basePath : dirname(__DIR__, 2);

        return $path !== '' ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }

    /**
     * @param class-string<\EzPhp\Contracts\ServiceProvider> $class
     *
     * @return $this
     */
    public function register(string $class): self
    {
        $this->userProviders[] = $class;

        return $this;
    }

    /**
     * Register a console command class to be included in the Console.
     *
     * @param class-string $commandClass
     *
     * @return $this
     */
    public function registerCommand(string $commandClass): static
    {
        $this->userCommands[] = $commandClass;

        return $this;
    }

    /**
     * Return all user-registered command class names.
     *
     * @return list<class-string>
     */
    public function getCommands(): array
    {
        return $this->userCommands;
    }

    /**
     * Register a short alias for a middleware class.
     *
     * @param string                            $alias Short name (e.g. 'auth', 'throttle').
     * @param class-string<MiddlewareInterface> $class Fully-qualified middleware class name.
     *
     * @return $this
     */
    public function middlewareAlias(string $alias, string $class): self
    {
        $this->middlewareAliases[$alias] = $class;

        return $this;
    }

    /**
     * Return all registered middleware aliases.
     *
     * @return array<string, class-string<MiddlewareInterface>>
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * Register a named middleware group.
     *
     * Groups allow multiple middlewares to be referenced by a single name on routes.
     * Example: $app->middlewareGroup('api', [AuthMiddleware::class, ThrottleMiddleware::class])
     *
     * @param string       $name    Short group name (e.g. 'api', 'web').
     * @param list<class-string<MiddlewareInterface>> $classes Middleware class-strings in the group.
     *
     * @return $this
     */
    public function middlewareGroup(string $name, array $classes): self
    {
        $this->middlewareGroups[$name] = $classes;

        return $this;
    }

    /**
     * Return all registered middleware groups.
     *
     * @return array<string, list<class-string<MiddlewareInterface>>>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Register one or more global middleware classes.
     *
     * @param class-string<MiddlewareInterface> ...$classes
     *
     * @return $this
     */
    public function middleware(string ...$classes): self
    {
        foreach ($classes as $class) {
            $this->globalMiddleware[] = $class;
        }

        return $this;
    }

    /**
     * @return void
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        $this->serviceProviders = [];
        $this->deferredBindings = [];
        $this->loadedDeferredProviders = [];
        $this->middlewarePushed = false;
        $this->foundation();
        $this->loadServiceProviders();
        $this->registerServiceProviders();
        $this->bootServiceProviders();

        $this->booted = true;
    }

    /**
     * Turn a request into a response.
     *
     * Pure: does not send output and does not call terminate() — use send()
     * for that.
     *
     * @param Request $request
     *
     * @return ResponseInterface
     * @throws ReflectionException
     */
    public function handle(Request $request): ResponseInterface
    {
        if (!$this->booted) {
            $this->bootstrap();
        }

        $handler = $this->make(MiddlewareHandler::class);

        if (!$this->middlewarePushed) {
            $handler->setAliases($this->middlewareAliases);
            $handler->setGroups($this->middlewareGroups);
            foreach ($this->globalMiddleware as $class) {
                $handler->add($class);
            }
            $this->middlewarePushed = true;
        }

        // Global middleware runs before routing so that middleware like
        // CorsMiddleware can intercept requests (e.g. OPTIONS preflight)
        // before a route is resolved.
        return $handler->dispatch($request, function (Request $request) use ($handler): ResponseInterface {
            try {
                $route = $this->make(Router::class)->retrieveRoute($request);
                return $handler->runRoute($route, $request);
            } catch (Throwable $e) {
                $exceptionHandler = $this->make(ExceptionHandlerInterface::class);
                $exceptionHandler->report($e, $request);

                return $exceptionHandler->render($e, $request);
            }
        });
    }

    /**
     * Send the response to the client, then run terminable middleware.
     *
     * A failure while a streamed body is being written happens after headers
     * were sent, so it is reported (not rendered). terminate() runs in every
     * case — after the last chunk, after a stream failure and after a client
     * disconnect.
     *
     * @param Request           $request
     * @param ResponseInterface $response
     * @param ResponseEmitter   $emitter
     *
     * @return void
     * @throws ReflectionException
     */
    public function send(Request $request, ResponseInterface $response, ResponseEmitter $emitter = new ResponseEmitter()): void
    {
        try {
            $emitter->emit($response, function (Throwable $e) use ($request): void {
                try {
                    $this->make(ExceptionHandlerInterface::class)->report($e, $request);
                } catch (Throwable) {
                    // Nothing else can catch this here, and terminate() must still run.
                }
            });
        } finally {
            $this->terminate($request, $response);
        }
    }

    /**
     * Run terminate() on every terminable middleware resolved for this request.
     *
     * @param Request           $request
     * @param ResponseInterface $response
     *
     * @return void
     * @throws ReflectionException
     */
    public function terminate(Request $request, ResponseInterface $response): void
    {
        $this->make(MiddlewareHandler::class)->terminate($request, $response);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     *
     * @return T
     * @throws ReflectionException
     */
    public function make(string $class): object
    {
        if (!isset($this->container)) {
            throw new ApplicationException('Container not set!');
        }

        $this->activateDeferredProviderFor($class);

        return $this->container->make($class);
    }

    /**
     * Create a redirect response to the given URL.
     *
     * @param string $url    Absolute or relative URL to redirect to.
     * @param int    $status HTTP status code (default: 302 Found).
     *
     * @return Response
     */
    public function redirect(string $url, int $status = 302): Response
    {
        return (new Response('', $status))->withHeader('Location', $url);
    }

    /**
     * Create a redirect response back to the previous page using the Referer header.
     * Falls back to '/' when the Referer header is absent or empty.
     *
     * @param RequestInterface $request
     * @param int              $status  HTTP status code (default: 302 Found).
     *
     * @return Response
     */
    public function back(RequestInterface $request, int $status = 302): Response
    {
        $referer = $request->header('referer', '');
        $url = is_string($referer) && $referer !== '' ? $referer : '/';

        return $this->redirect($url, $status);
    }

    /**
     * Generate a URL for a named route.
     *
     * Delegates to Router::route(). The Router must be bootstrapped (i.e. routes
     * must have been loaded via RouterServiceProvider::boot()) before calling this.
     *
     * Example:
     *   $app->route('user.show', ['id' => 42]);  // → '/users/42'
     *
     * @param string               $name   The route name as registered via ->name('...').
     * @param array<string, string> $params Named parameter values to substitute into the path.
     *
     * @return string
     * @throws \EzPhp\Exceptions\RouteException When no named route with the given name exists.
     * @throws ReflectionException
     */
    public function route(string $name, array $params = []): string
    {
        return $this->make(Router::class)->route($name, $params);
    }

    /**
     * Register an existing object as a shared instance in the container.
     * Useful in boot() to decorate a previously resolved service.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param T               $instance
     *
     * @return void
     */
    public function instance(string $class, object $instance): void
    {
        $this->container->instance($class, $instance);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param class-string|callable|null $value
     *
     * @return static
     */
    public function bind(string $class, string|callable|null $value = null): static
    {
        if ($value === null) {
            $this->container->bind($class);
        } elseif (is_string($value)) {
            $this->container->bind($class, $value);
        } else {
            $this->container->bind($class, fn () => $value($this));
        }

        return $this;
    }

    /**
     * @return void
     */
    private function foundation(): void
    {
        $this->container = new Container();
        $this->container->bind(Application::class, fn () => $this);
        $this->container->bind(ContainerInterface::class, fn () => $this);
        $this->container->bind(Container::class, fn () => $this->container);
    }

    /**
     * @return void
     */
    private function loadServiceProviders(): void
    {
        $providers = array_merge(CoreServiceProviders::all(), $this->userProviders);
        foreach ($providers as $serviceProviderClass) {
            $instance = new $serviceProviderClass($this);

            if ($instance->deferred()) {
                foreach ($instance->provides() as $binding) {
                    $this->deferredBindings[$binding] = $serviceProviderClass;
                }
                continue;
            }

            $this->serviceProviders[] = $instance;
        }
    }

    /**
     * Activate the deferred provider responsible for the given binding class,
     * if one has been registered and has not yet been loaded.
     *
     * register() always runs immediately, so the binding is available to the
     * make() call that triggered activation. boot() normally runs right
     * after it too — except when this activation was itself triggered from
     * inside another deferred provider's register()/boot() (that provider's
     * boot() resolved a class belonging to a second, not-yet-loaded deferred
     * provider). In that nested case, boot() is queued and runs immediately
     * after the outer provider's own boot() completes instead of interleaving
     * mid-boot, preserving the register-before-boot ordering guarantee.
     *
     * @param string $class
     *
     * @return void
     */
    private function activateDeferredProviderFor(string $class): void
    {
        if (!isset($this->deferredBindings[$class])) {
            return;
        }

        $providerClass = $this->deferredBindings[$class];

        if (isset($this->loadedDeferredProviders[$providerClass])) {
            return;
        }

        $this->loadedDeferredProviders[$providerClass] = true;

        $provider = new $providerClass($this);
        $provider->register();

        if ($this->activatingDeferredProvider) {
            $this->deferredBootQueue[] = $provider;
            return;
        }

        $this->activatingDeferredProvider = true;

        try {
            $provider->boot();

            while ($queued = array_shift($this->deferredBootQueue)) {
                $queued->boot();
            }
        } finally {
            $this->activatingDeferredProvider = false;
        }
    }

    /**
     * @return void
     */
    private function registerServiceProviders(): void
    {
        foreach ($this->serviceProviders as $serviceProvider) {
            $serviceProvider->register();
        }
    }

    /**
     * @return void
     */
    private function bootServiceProviders(): void
    {
        foreach ($this->serviceProviders as $serviceProvider) {
            $serviceProvider->boot();
        }
    }
}
