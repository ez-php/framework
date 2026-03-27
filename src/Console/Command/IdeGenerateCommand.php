<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Class IdeGenerateCommand
 *
 * Generates a `_ide_helpers.php` file containing PHPDoc stubs for all
 * registered static façades (Auth::, Event::, Http::, Mail::, Broadcast::,
 * View::, …). IDEs use this file for autocompletion without loading it at
 * runtime.
 *
 * Usage:
 *   ez ide:generate [--output=_ide_helpers.php]
 *
 * Only façades that are actually installed (class_exists) are included.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final class IdeGenerateCommand implements CommandInterface
{
    /**
     * Well-known static façade class names. Only those present in the
     * autoloader (class_exists) will appear in the generated file.
     *
     * @var list<string>
     */
    private const array KNOWN_FACADES = [
        'EzPhp\Auth\Auth',
        'EzPhp\Broadcast\Broadcast',
        'EzPhp\Events\Event',
        'EzPhp\HttpClient\Http',
        'EzPhp\Mail\Mail',
        'EzPhp\RateLimiter\RateLimiter',
        'EzPhp\View\View',
    ];

    /**
     * IdeGenerateCommand Constructor
     *
     * @param string $basePath Absolute path to the application root (output goes here by default).
     */
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'ide:generate';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Generate IDE helper stubs for static façades';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez ide:generate [--output=_ide_helpers.php]\n\nGenerates PHPDoc @method stubs for all installed façades so your IDE can autocomplete Auth::, Event::, etc.";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);
        $output = $input->option('output', $this->basePath . DIRECTORY_SEPARATOR . '_ide_helpers.php');

        $facades = $this->resolveInstalledFacades($input->option('facades', ''));

        if ($facades === []) {
            echo "No installed façades found — nothing to generate.\n";
            return 0;
        }

        $blocks = [];
        foreach ($facades as $class) {
            $block = $this->generateBlock($class);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        $content = $this->buildFile($blocks);

        if (file_put_contents($output, $content) === false) {
            fwrite(STDERR, "Failed to write: $output\n");
            return 1;
        }

        echo 'Generated: ' . $output . ' (' . count($blocks) . ' façade(s))' . "\n";
        return 0;
    }

    /**
     * Return the list of façade class names to generate stubs for.
     * Merges KNOWN_FACADES with any extra classes supplied via --facades.
     *
     * @param string $extraOption Comma-separated extra class names from --facades option.
     *
     * @return list<class-string>
     */
    private function resolveInstalledFacades(string $extraOption): array
    {
        $candidates = self::KNOWN_FACADES;

        if ($extraOption !== '') {
            foreach (explode(',', $extraOption) as $cls) {
                $cls = trim($cls);
                if ($cls !== '') {
                    $candidates[] = $cls;
                }
            }
        }

        /** @var list<class-string> $installed */
        $installed = array_values(array_filter($candidates, static fn (string $cls): bool => class_exists($cls)));

        return $installed;
    }

    /**
     * Generate a namespace block with @method static PHPDoc for one façade class.
     *
     * @param class-string $class
     *
     * @return string|null Null when no public static methods are found.
     */
    private function generateBlock(string $class): ?string
    {
        $ref = new ReflectionClass($class);
        $methods = array_filter(
            $ref->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC),
            static fn (ReflectionMethod $m): bool => !$m->isConstructor()
                && $m->getDeclaringClass()->getName() === $class,
        );

        if ($methods === []) {
            return null;
        }

        $lines = [];
        foreach ($methods as $method) {
            $lines[] = ' * @method static ' . $this->renderSignature($method);
        }

        $shortName = $ref->getShortName();
        $namespace = $ref->getNamespaceName();

        $doc = implode("\n", [
            '    /**',
            ' * IDE helper stubs for \\' . $class . '.',
            ' *',
            ...$lines,
            ' * @see \\' . $class,
            ' */',
        ]);

        return <<<PHP
            namespace $namespace {
                $doc
                class $shortName {}
            }
            PHP;
    }

    /**
     * Render a single method's return type and parameter list for a @method tag.
     *
     * @param ReflectionMethod $method
     *
     * @return string  e.g. "bool check()" or "void login(UserInterface \$user)"
     */
    private function renderSignature(ReflectionMethod $method): string
    {
        $returnType = $this->renderType($method->getReturnType());
        $params = array_map(
            fn (ReflectionParameter $p): string => $this->renderParam($p),
            $method->getParameters(),
        );

        return $returnType . ' ' . $method->getName() . '(' . implode(', ', $params) . ')';
    }

    /**
     * Render a parameter as a string: "TypeHint $name" or "$name = default".
     *
     * @param ReflectionParameter $param
     *
     * @return string
     */
    private function renderParam(ReflectionParameter $param): string
    {
        $type = $this->renderType($param->getType());
        $name = '$' . $param->getName();

        $sig = $type !== '' ? "$type $name" : $name;

        if ($param->isVariadic()) {
            $sig = ($type !== '' ? "$type " : '') . '...$' . $param->getName();
        }

        if ($param->isOptional() && !$param->isVariadic()) {
            try {
                $default = $param->getDefaultValue();
                $sig .= ' = ' . $this->exportDefault($default);
            } catch (\ReflectionException) {
                $sig .= ' = null';
            }
        }

        return $sig;
    }

    /**
     * Render a ReflectionType (named, union, intersection, or nullable) as a string.
     *
     * @param \ReflectionType|null $type
     *
     * @return string
     */
    private function renderType(?\ReflectionType $type): string
    {
        if ($type === null) {
            return '';
        }

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();
            // Qualify non-builtin types with backslash so IDEs resolve them
            if (!$type->isBuiltin()) {
                $name = '\\' . $name;
            }
            return $type->allowsNull() && $name !== 'null' && $name !== 'mixed' ? "?$name" : $name;
        }

        if ($type instanceof ReflectionUnionType) {
            return implode('|', array_map(
                fn (\ReflectionType $t): string => $this->renderType($t),
                $type->getTypes(),
            ));
        }

        // IntersectionType or future types — fall back to string representation
        return (string) $type;
    }

    /**
     * Export a default parameter value as a PHP literal string.
     *
     * @param mixed $value
     *
     * @return string
     */
    private function exportDefault(mixed $value): string
    {
        return match (true) {
            is_null($value) => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value),
            is_float($value) => (string) $value,
            is_string($value) => "'" . addslashes($value) . "'",
            is_array($value) => '[]',
            default => 'null',
        };
    }

    /**
     * Wrap namespace blocks in a complete PHP file with header comment.
     *
     * @param list<string> $blocks
     *
     * @return string
     */
    private function buildFile(array $blocks): string
    {
        $date = date('Y-m-d H:i:s');
        $content = implode("\n\n", $blocks);

        return <<<PHP
            <?php

            // @noinspection ALL

            /**
             * IDE helper file — auto-generated by `ez ide:generate` on $date.
             *
             * This file is NOT loaded at runtime and must NOT be added to any autoloader.
             * It exists solely to provide IDE autocompletion for ez-php static façades.
             *
             * Re-generate after installing or updating modules:
             *   php ez ide:generate
             */

            $content
            PHP . "\n";
    }
}
