<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Application\Application;
use EzPhp\Console\Command\MakeChannelCommand;
use EzPhp\Console\Command\MakeCommandCommand;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeEventCommand;
use EzPhp\Console\Command\MakeJobCommand;
use EzPhp\Console\Command\MakeListenerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeNotificationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MakeRequestCommand;
use EzPhp\Console\CommandInterface;
use EzPhp\Console\ConsoleServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class MakeCommandPathsTest
 *
 * Pins **where** `ConsoleServiceProvider` makes each `make:*` generator write.
 *
 * This exists because `make:controller`, `make:middleware` and `make:provider`
 * were bound to `basePath('src')` while their stubs declare `namespace App\…`
 * and the application template autoloads `App\ => app/` with no `src/` directory
 * at all — so the generated classes landed somewhere Composer never autoloads.
 *
 * `MakeCommandsTest` did not catch it: it constructs each command with a temp
 * directory of its own and asserts paths relative to that, so the directory the
 * *provider* chooses was never exercised. These tests resolve the commands
 * through the container instead, which is the path a real `ez make:…` takes.
 *
 * The Application is bootstrapped against a temp base path, which is exactly what
 * `ez make:…` does. No database is touched: the command bindings are lazy closures
 * that need nothing but `basePath()`.
 *
 * @package Tests\Console
 */
#[CoversClass(ConsoleServiceProvider::class)]
#[UsesClass(Application::class)]
#[UsesClass(MakeControllerCommand::class)]
#[UsesClass(MakeMiddlewareCommand::class)]
#[UsesClass(MakeProviderCommand::class)]
#[UsesClass(MakeEventCommand::class)]
#[UsesClass(MakeListenerCommand::class)]
#[UsesClass(MakeJobCommand::class)]
#[UsesClass(MakeNotificationCommand::class)]
#[UsesClass(MakeChannelCommand::class)]
#[UsesClass(MakeRequestCommand::class)]
#[UsesClass(MakeCommandCommand::class)]
final class MakeCommandPathsTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->basePath = sys_get_temp_dir() . '/ez-php-make-paths-' . uniqid();
        mkdir($this->basePath, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->basePath);

        parent::tearDown();
    }

    /**
     * Every `make:*` generator that emits an `App\…` class, and the sub-directory
     * it must create under the application source root.
     *
     * @return array<string, array{class-string, string}>
     */
    public static function appClassGenerators(): array
    {
        return [
            'make:controller' => [MakeControllerCommand::class, 'Controllers'],
            'make:middleware' => [MakeMiddlewareCommand::class, 'Middleware'],
            'make:provider' => [MakeProviderCommand::class, 'Providers'],
            'make:event' => [MakeEventCommand::class, 'Events'],
            'make:listener' => [MakeListenerCommand::class, 'Listeners'],
            'make:job' => [MakeJobCommand::class, 'Jobs'],
            'make:notification' => [MakeNotificationCommand::class, 'Notifications'],
            'make:channel' => [MakeChannelCommand::class, 'Channels'],
            'make:request' => [MakeRequestCommand::class, 'Requests'],
            'make:command' => [MakeCommandCommand::class, 'Console'],
        ];
    }

    /**
     * @param class-string $commandClass
     * @param string       $subDir
     *
     * @return void
     */
    #[DataProvider('appClassGenerators')]
    public function test_generator_writes_below_the_autoloaded_app_directory(
        string $commandClass,
        string $subDir,
    ): void {
        $app = new Application($this->basePath);
        $app->bootstrap();

        $command = $app->make($commandClass);
        self::assertInstanceOf(CommandInterface::class, $command);

        ob_start();
        $code = $command->handle(['Generated']);
        ob_get_clean();

        self::assertSame(0, $code);
        self::assertFileExists(
            $this->basePath . '/app/' . $subDir . '/Generated.php',
            $command->getName() . ' must write below app/, the directory the template autoloads.',
        );
        self::assertDirectoryDoesNotExist(
            $this->basePath . '/src',
            $command->getName() . ' must not create a src/ directory — nothing autoloads it.',
        );
    }

    /**
     * The generated file declares `namespace App\…`, so it is only loadable if it
     * sits in the directory the template maps `App\` to. Pin the two together: if
     * the template's PSR-4 target ever moves, this fails instead of silently
     * producing unloadable classes again.
     *
     * Skipped when the template is absent (the framework package is also released
     * standalone, without the monorepo around it).
     *
     * @return void
     */
    public function test_app_directory_matches_the_templates_psr4_target(): void
    {
        $templateComposer = dirname(__DIR__, 3) . '/ez-php/composer.json';

        if (!is_file($templateComposer)) {
            self::markTestSkipped('Application template not present (standalone framework checkout).');
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($templateComposer), true);
        self::assertIsArray($decoded);

        $autoload = $decoded['autoload'] ?? null;
        self::assertIsArray($autoload);

        $psr4 = $autoload['psr-4'] ?? null;
        self::assertIsArray($psr4);

        $target = $psr4['App\\'] ?? null;
        self::assertSame(
            'app/',
            $target,
            'The generators write to app/; the template must map App\\ to the same directory.',
        );
    }

    /**
     * Recursively delete a directory tree.
     *
     * @param string $path
     *
     * @return void
     */
    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $entry;

            is_dir($full) ? $this->removeDir($full) : unlink($full);
        }

        rmdir($path);
    }
}
