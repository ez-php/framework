<?php

declare(strict_types=1);

use EzPhp\Application\Application;
use EzPhp\Http\RequestFactory;
use EzPhp\Routing\Router;
use Tests\TestCase;

/**
 * Class ExampleTest
 *
 * @package Tests
 */
final class TestTest extends TestCase
{
    /**
     * @return void
     */
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    public function test_test(): void
    {
        $_SERVER['REQUEST_URI'] = '/test';
        $request = RequestFactory::createFromGlobals();

        $app = new Application();
        $app->bootstrap();

        $router = $app->make(Router::class);
        $router->add('GET', '/test', fn () => 'Hello World!');

        $response = $app->handle($request);

        $this->assertSame(200, $response->status());
        $this->assertSame('Hello World!', $response->body());
    }
}
