<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeEventCommand;
use EzPhp\Console\Command\MakeListenerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeModelCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MakeRequestCommand;
use EzPhp\Console\Command\MakeTestCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class MakeCommandsTest
 *
 * Covers make:controller, make:middleware, make:provider, make:model,
 * make:event, make:listener, make:request, and make:test commands.
 *
 * @package Tests\Console\Command
 */
#[CoversClass(MakeControllerCommand::class)]
#[CoversClass(MakeMiddlewareCommand::class)]
#[CoversClass(MakeProviderCommand::class)]
#[CoversClass(MakeModelCommand::class)]
#[CoversClass(MakeEventCommand::class)]
#[CoversClass(MakeListenerCommand::class)]
#[CoversClass(MakeRequestCommand::class)]
#[CoversClass(MakeTestCommand::class)]
final class MakeCommandsTest extends TestCase
{
    private string $srcPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->srcPath = sys_get_temp_dir() . '/ez-php-make-' . uniqid();
        mkdir($this->srcPath, 0o755, true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDir($this->srcPath);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param string $path
     *
     * @return void
     */
    private function removeDir(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
        }
        rmdir($path);
    }

    // ─── make:controller ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_controller_creates_file(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['UserController']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Controllers/UserController.php');
    }

    /**
     * @return void
     */
    public function test_make_controller_stub_contains_class_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $cmd->handle(['PostController']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Controllers/PostController.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('PostController', $content);
        $this->assertStringContainsString('EzPhp\\Http\\Request', $content);
    }

    /**
     * @return void
     */
    public function test_make_controller_returns_1_without_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_controller_returns_1_if_already_exists(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $cmd->handle(['UserController']);
        $code = $cmd->handle(['UserController']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_controller_returns_1_for_invalid_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['123invalid']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_controller_get_name(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);
        $this->assertSame('make:controller', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_controller_get_description(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_controller_get_help(): void
    {
        $cmd = new MakeControllerCommand($this->srcPath);
        $this->assertStringContainsString('make:controller', $cmd->getHelp());
    }

    // ─── make:middleware ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_middleware_creates_file(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['AuthMiddleware']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Middleware/AuthMiddleware.php');
    }

    /**
     * @return void
     */
    public function test_make_middleware_stub_implements_middleware_interface(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $cmd->handle(['CorsMiddleware']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Middleware/CorsMiddleware.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('MiddlewareInterface', $content);
        $this->assertStringContainsString('CorsMiddleware', $content);
    }

    /**
     * @return void
     */
    public function test_make_middleware_returns_1_without_name(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_middleware_returns_1_if_already_exists(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);

        ob_start();
        $cmd->handle(['AuthMiddleware']);
        $code = $cmd->handle(['AuthMiddleware']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_middleware_get_name(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);
        $this->assertSame('make:middleware', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_middleware_get_description(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_middleware_get_help(): void
    {
        $cmd = new MakeMiddlewareCommand($this->srcPath);
        $this->assertStringContainsString('make:middleware', $cmd->getHelp());
    }

    // ─── make:provider ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_provider_creates_file(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['AppServiceProvider']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Providers/AppServiceProvider.php');
    }

    /**
     * @return void
     */
    public function test_make_provider_stub_extends_service_provider(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $cmd->handle(['MyProvider']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Providers/MyProvider.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('ServiceProvider', $content);
        $this->assertStringContainsString('MyProvider', $content);
    }

    /**
     * @return void
     */
    public function test_make_provider_returns_1_without_name(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_provider_returns_1_if_already_exists(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);

        ob_start();
        $cmd->handle(['AppServiceProvider']);
        $code = $cmd->handle(['AppServiceProvider']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_provider_get_name(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);
        $this->assertSame('make:provider', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_provider_get_description(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_provider_get_help(): void
    {
        $cmd = new MakeProviderCommand($this->srcPath);
        $this->assertStringContainsString('make:provider', $cmd->getHelp());
    }

    // ─── make:model ──────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_model_creates_file(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['Post']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Models/Post.php');
    }

    /**
     * @return void
     */
    public function test_make_model_stub_extends_model(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);

        ob_start();
        $cmd->handle(['User']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Models/User.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('User', $content);
        $this->assertStringContainsString('extends Model', $content);
        $this->assertStringContainsString('EzPhp\\Orm\\Model', $content);
    }

    /**
     * @return void
     */
    public function test_make_model_stub_has_table_property(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);

        ob_start();
        $cmd->handle(['BlogPost']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Models/BlogPost.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('$table', $content);
    }

    /**
     * @return void
     */
    public function test_make_model_returns_1_without_name(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_model_returns_1_if_already_exists(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);

        ob_start();
        $cmd->handle(['Post']);
        $code = $cmd->handle(['Post']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_model_get_name(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);
        $this->assertSame('make:model', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_model_get_description(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_model_get_help(): void
    {
        $cmd = new MakeModelCommand($this->srcPath);
        $this->assertStringContainsString('make:model', $cmd->getHelp());
    }

    // ─── make:event ──────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_event_creates_file(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['UserCreated']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Events/UserCreated.php');
    }

    /**
     * @return void
     */
    public function test_make_event_stub_implements_event_interface(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);

        ob_start();
        $cmd->handle(['OrderPlaced']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Events/OrderPlaced.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('OrderPlaced', $content);
        $this->assertStringContainsString('EventInterface', $content);
    }

    /**
     * @return void
     */
    public function test_make_event_returns_1_without_name(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_event_returns_1_if_already_exists(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);

        ob_start();
        $cmd->handle(['UserCreated']);
        $code = $cmd->handle(['UserCreated']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_event_get_name(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);
        $this->assertSame('make:event', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_event_get_description(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_event_get_help(): void
    {
        $cmd = new MakeEventCommand($this->srcPath);
        $this->assertStringContainsString('make:event', $cmd->getHelp());
    }

    // ─── make:listener ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_listener_creates_file(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['SendWelcomeEmail']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Listeners/SendWelcomeEmail.php');
    }

    /**
     * @return void
     */
    public function test_make_listener_stub_implements_listener_interface(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);

        ob_start();
        $cmd->handle(['NotifyAdmin']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Listeners/NotifyAdmin.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('NotifyAdmin', $content);
        $this->assertStringContainsString('ListenerInterface', $content);
        $this->assertStringContainsString('handle(EventInterface', $content);
    }

    /**
     * @return void
     */
    public function test_make_listener_returns_1_without_name(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_listener_returns_1_if_already_exists(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);

        ob_start();
        $cmd->handle(['SendWelcomeEmail']);
        $code = $cmd->handle(['SendWelcomeEmail']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_listener_get_name(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);
        $this->assertSame('make:listener', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_listener_get_description(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_listener_get_help(): void
    {
        $cmd = new MakeListenerCommand($this->srcPath);
        $this->assertStringContainsString('make:listener', $cmd->getHelp());
    }

    // ─── make:request ────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_request_creates_file(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['StoreUserRequest']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Requests/StoreUserRequest.php');
    }

    /**
     * @return void
     */
    public function test_make_request_stub_has_rules_method(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);

        ob_start();
        $cmd->handle(['CreatePostRequest']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Requests/CreatePostRequest.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('CreatePostRequest', $content);
        $this->assertStringContainsString('rules()', $content);
        $this->assertStringContainsString('validate(', $content);
    }

    /**
     * @return void
     */
    public function test_make_request_returns_1_without_name(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_request_returns_1_if_already_exists(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);

        ob_start();
        $cmd->handle(['StoreUserRequest']);
        $code = $cmd->handle(['StoreUserRequest']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_request_get_name(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);
        $this->assertSame('make:request', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_request_get_description(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_request_get_help(): void
    {
        $cmd = new MakeRequestCommand($this->srcPath);
        $this->assertStringContainsString('make:request', $cmd->getHelp());
    }

    // ─── make:test ───────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_make_test_creates_unit_test_by_default(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['UserTest']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/UserTest.php');
    }

    /**
     * @return void
     */
    public function test_make_test_unit_stub_extends_test_case(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $cmd->handle(['MyUnitTest', 'unit']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/MyUnitTest.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('MyUnitTest', $content);
        $this->assertStringContainsString('TestCase', $content);
    }

    /**
     * @return void
     */
    public function test_make_test_feature_stub_extends_application_test_case(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $cmd->handle(['MyFeatureTest', 'feature']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/MyFeatureTest.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('ApplicationTestCase', $content);
    }

    /**
     * @return void
     */
    public function test_make_test_http_stub_extends_http_test_case(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $cmd->handle(['MyHttpTest', 'http']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/MyHttpTest.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('HttpTestCase', $content);
    }

    /**
     * @return void
     */
    public function test_make_test_returns_1_for_invalid_type(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle(['SomeTest', 'invalid']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_test_returns_1_without_name(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $code = $cmd->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_test_returns_1_if_already_exists(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);

        ob_start();
        $cmd->handle(['UserTest']);
        $code = $cmd->handle(['UserTest']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_make_test_get_name(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);
        $this->assertSame('make:test', $cmd->getName());
    }

    /**
     * @return void
     */
    public function test_make_test_get_description(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);
        $this->assertNotEmpty($cmd->getDescription());
    }

    /**
     * @return void
     */
    public function test_make_test_get_help(): void
    {
        $cmd = new MakeTestCommand($this->srcPath);
        $this->assertStringContainsString('make:test', $cmd->getHelp());
    }
}
