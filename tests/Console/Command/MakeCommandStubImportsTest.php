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
    /**
     * Run in a child process: loads the autoloader ($argv[1]) and the generated
     * file ($argv[2]), then exits 1 unless the file returned an object or
     * itself declared a non-abstract, instantiable class. A missing interface method
     * is a fatal error, which also exits non-zero.
     */
    private const string LOADER = <<<'PHP'
        require $argv[1];
        $before = get_declared_classes();
        $result = require $argv[2];
        $classes = is_object($result) ? [$result::class] : array_filter(
            array_diff(get_declared_classes(), $before),
            static fn (string $c): bool => (new ReflectionClass($c))->getFileName() === realpath($argv[2]),
        );
        if ($classes === []) {
            fwrite(STDERR, "no class declared or returned\n");
            exit(1);
        }
        foreach ($classes as $class) {
            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || !$ref->isInstantiable()) {
                fwrite(STDERR, "$class is not instantiable\n");
                exit(1);
            }
        }
        PHP;

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
            'make:channel' => [static fn (string $d): CommandInterface => new MakeChannelCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubChannel']],
            'make:command' => [static fn (string $d): CommandInterface => new MakeCommandCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubCommand']],
            'make:controller' => [static fn (string $d): CommandInterface => new MakeControllerCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubController']],
            'make:event' => [static fn (string $d): CommandInterface => new MakeEventCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubEvent']],
            'make:job' => [static fn (string $d): CommandInterface => new MakeJobCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubJob']],
            'make:listener' => [static fn (string $d): CommandInterface => new MakeListenerCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubListener']],
            'make:middleware' => [static fn (string $d): CommandInterface => new MakeMiddlewareCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubMiddleware']],
            'make:migration create' => [static fn (string $d): CommandInterface => new MakeMigrationCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['create_stubs_table']],
            'make:migration alter' => [static fn (string $d): CommandInterface => new MakeMigrationCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['add_flag_to_stubs_table']],
            'make:migration plain' => [static fn (string $d): CommandInterface => new MakeMigrationCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['backfill_stub_data']],
            'make:notification' => [static fn (string $d): CommandInterface => new MakeNotificationCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubNotification']],
            'make:provider' => [static fn (string $d): CommandInterface => new MakeProviderCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubProvider']],
            'make:request' => [static fn (string $d): CommandInterface => new MakeRequestCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubRequest']],
            'make:seeder' => [static fn (string $d): CommandInterface => new MakeSeederCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubSeeder']],
            'make:test unit' => [static fn (string $d): CommandInterface => new MakeTestCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubUnitTest', 'unit']],
            'make:test feature' => [static fn (string $d): CommandInterface => new MakeTestCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubFeatureTest', 'feature']],
            'make:test http' => [static fn (string $d): CommandInterface => new MakeTestCommand($d, errorStream: fopen('php://memory', 'w') ?: null), ['StubHttpTest', 'http']],
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
     * Resolving imports is not enough: a stub that implements an interface but
     * misses one of its methods (make:job once omitted three JobInterface
     * methods) imports fine and fatals on load. Load each generated file in a
     * separate PHP process — a fatal there fails this test instead of killing
     * the suite — and require the declared or returned class to be concrete.
     *
     * @param \Closure(string): CommandInterface $factory
     * @param list<string>                       $args
     *
     * @return void
     */
    #[DataProvider('generators')]
    public function test_generated_class_loads_and_is_instantiable(\Closure $factory, array $args): void
    {
        ob_start();

        try {
            $exitCode = $factory($this->dir)->handle($args);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(0, $exitCode);

        $checked = 0;

        foreach ($this->phpFiles($this->dir) as $file) {
            preg_match_all('/^use (EzPhp\\\\[A-Za-z0-9_\\\\]+);/m', (string) file_get_contents($file), $m);

            foreach ($m[1] as $class) {
                if (!$this->packageInstalledFor($class)) {
                    continue 2;
                }
            }

            $output = [];
            exec(
                escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg(self::LOADER)
                . ' -- ' . escapeshellarg($this->autoloadPath()) . ' ' . escapeshellarg($file) . ' 2>&1',
                $output,
                $status,
            );

            $this->assertSame(0, $status, sprintf("%s did not load as a concrete class:\n%s", basename($file), implode("\n", $output)));
            $checked++;
        }

        if ($checked === 0) {
            $this->markTestSkipped('the generated stub depends on an optional module that is not installed');
        }
    }

    /**
     * The Composer autoloader of the running test suite.
     */
    private function autoloadPath(): string
    {
        $loaderFile = (string) (new \ReflectionClass(ClassLoader::class))->getFileName();

        return dirname($loaderFile, 2) . DIRECTORY_SEPARATOR . 'autoload.php';
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
