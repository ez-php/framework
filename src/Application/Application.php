<?php

declare(strict_types=1);

namespace EzPhp\Application;

use EzPhp\Container\Container;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ExceptionHandlerInterface;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Http\Request;
use EzPhp\Http\Response;
use EzPhp\Middleware\MiddlewareHandler;
use EzPhp\Middleware\MiddlewareInterface;
use EzPhp\Routing\Router;
use ReflectionException;
use Throwable;

/**
 * Class Application
 *
 * @package EzPhp\Application
 */
final class Application implements ContainerInterface
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
    public function registerCommand(string $commandClass): self
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
     * @param Request $request
     *
     * @return Response
     * @throws ReflectionException
     */
    public function handle(Request $request): Response
    {
        if (!$this->booted) {
            $this->bootstrap();
        }

        $handler = $this->make(MiddlewareHandler::class);

        if (!$this->middlewarePushed) {
            $handler->setAliases($this->middlewareAliases);
            foreach ($this->globalMiddleware as $class) {
                $handler->add($class);
            }
            $this->middlewarePushed = true;
        }

        // Global middleware runs before routing so that middleware like
        // CorsMiddleware can intercept requests (e.g. OPTIONS preflight)
        // before a route is resolved.
        $response = $handler->dispatch($request, function (Request $request) use ($handler): Response {
            try {
                $route = $this->make(Router::class)->retrieveRoute($request);
                return $handler->runRoute($route, $request);
            } catch (Throwable $e) {
                return $this->make(ExceptionHandlerInterface::class)->render($e, $request);
            }
        });

        $handler->terminate($request, $response);

        return $response;
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
        $provider->boot();
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
