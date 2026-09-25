<?php

declare(strict_types=1);

namespace Tests\Console\Command;

use Composer\Autoload\ClassLoader;
use EzPhp\Console\Command\MakeChannelCommand;
use EzPhp\Console\Command\MakeCommandCommand;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeEventCommand;
use EzPhp\Console\Command\MakeJobCommand;
use EzPhp\Console\Command\MakeListenerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeNotificationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MakeRequestCommand;
use EzPhp\Console\Command\MakeSeederCommand;
use EzPhp\Console\Command\MakeTestCommand;
use EzPhp\Console\CommandInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every make:* stub must only import classes that exist.
 *
 * The string-matching tests in MakeCommandsTest cannot notice a stub that
 * imports a renamed or removed class (make:model extended the removed
 * EzPhp\Orm\Model; make:test imported EzPhp\Testing\Application\*). This test
 * generates each stub and resolves every `use EzPhp\...` it contains.
 *
 * Imports from an optional module that is not installed (e.g. ez-php/events in
 * the framework's standalone CI) are skipped; imports from an installed package
 * must resolve.
 *
 * @package Tests\Console\Command
 */
#[CoversNothing]
final class MakeCommandStubImportsTest extends TestCase
{
    private string $dir;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/ez-php-stub-imports-' . uniqid('', true);
        mkdir($this->dir, 0o755, true);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    /**
     * @return array<string, array{\Closure(string): CommandInterface, list<string>}>
     */
    public static function generators(): array
    {
        return [
            'make:channel' => [static fn (string $d): CommandInterface => new MakeChannelCommand($d), ['StubChannel']],
            'make:command' => [static fn (string $d): CommandInterface => new MakeCommandCommand($d), ['StubCommand']],
            'make:controller' => [static fn (string $d): CommandInterface => new MakeControllerCommand($d), ['StubController']],
            'make:event' => [static fn (string $d): CommandInterface => new MakeEventCommand($d), ['StubEvent']],
            'make:job' => [static fn (string $d): CommandInterface => new MakeJobCommand($d), ['StubJob']],
            'make:listener' => [static fn (string $d): CommandInterface => new MakeListenerCommand($d), ['StubListener']],
            'make:middleware' => [static fn (string $d): CommandInterface => new MakeMiddlewareCommand($d), ['StubMiddleware']],
            'make:migration create' => [static fn (string $d): CommandInterface => new MakeMigrationCommand($d), ['create_stubs_table']],
            'make:migration alter' => [static fn (string $d): CommandInterface => new MakeMigrationCommand($d), ['add_flag_to_stubs_table']],
            'make:migration plain' => [static fn (string $d): CommandInterface => new MakeMigrationCommand($d), ['backfill_stub_data']],
            'make:notification' => [static fn (string $d): CommandInterface => new MakeNotificationCommand($d), ['StubNotification']],
            'make:provider' => [static fn (string $d): CommandInterface => new MakeProviderCommand($d), ['StubProvider']],
            'make:request' => [static fn (string $d): CommandInterface => new MakeRequestCommand($d), ['StubRequest']],
            'make:seeder' => [static fn (string $d): CommandInterface => new MakeSeederCommand($d), ['StubSeeder']],
            'make:test unit' => [static fn (string $d): CommandInterface => new MakeTestCommand($d), ['StubUnitTest', 'unit']],
            'make:test feature' => [static fn (string $d): CommandInterface => new MakeTestCommand($d), ['StubFeatureTest', 'feature']],
            'make:test http' => [static fn (string $d): CommandInterface => new MakeTestCommand($d), ['StubHttpTest', 'http']],
        ];
    }

    /**
     * @param \Closure(string): CommandInterface $factory
     * @param list<string>                       $args
     *
     * @return void
     */
    #[DataProvider('generators')]
    public function test_generated_stub_imports_resolve(\Closure $factory, array $args): void
    {
        ob_start();

        try {
            $exitCode = $factory($this->dir)->handle($args);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(0, $exitCode);

        $files = $this->phpFiles($this->dir);
        $this->assertNotEmpty($files, 'the generator wrote no PHP file');

        foreach ($files as $file) {
            preg_match_all('/^use (EzPhp\\\\[A-Za-z0-9_\\\\]+);/m', (string) file_get_contents($file), $m);

            foreach ($m[1] as $class) {
                if (!$this->packageInstalledFor($class)) {
                    continue;
                }

                $this->assertTrue(
                    class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class),
                    sprintf('%s imports %s, which does not exist.', basename($file), $class),
                );
            }
        }
    }

    /**
     * Whether the Composer package that owns $class's namespace is installed.
     *
     * The framework maps the bare `EzPhp\` prefix; a first namespace segment
     * that is not one of its own src/ directories belongs to an optional module.
     */
    private function packageInstalledFor(string $class): bool
    {
        $segment = explode('\\', $class)[1] ?? '';

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
                if ($prefix !== 'EzPhp\\' && str_starts_with($class, $prefix)) {
                    return true;
                }

                if ($prefix === 'EzPhp\\') {
                    foreach ($dirs as $dir) {
                        if (is_dir($dir . DIRECTORY_SEPARATOR . $segment)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function removeDir(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
        }

        rmdir($path);
    }
}
