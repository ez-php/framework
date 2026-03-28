<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeTestCommand
 *
 * Scaffolds a PHPUnit test class. Supports three types:
 * - unit    — plain PHPUnit TestCase (default)
 * - feature — extends ApplicationTestCase from ez-php/testing-application
 * - http    — extends HttpTestCase from ez-php/testing-application
 *
 * Usage:
 *   ez make:test UserTest
 *   ez make:test UserTest unit
 *   ez make:test CreateUserTest feature
 *   ez make:test UserEndpointTest http
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeTestCommand implements CommandInterface
{
    private const VALID_TYPES = ['unit', 'feature', 'http'];

    /**
     * MakeTestCommand Constructor
     *
     * @param string $testsPath Absolute path to the tests/ directory.
     */
    public function __construct(private string $testsPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:test';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new test class (unit, feature, or http)';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:test <ClassName> [unit|feature|http]';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name)) {
            fwrite(STDERR, "Usage: ez make:test <ClassName> [unit|feature|http]\n");
            return 1;
        }

        $type = isset($args[1]) ? strtolower($args[1]) : 'unit';

        if (!in_array($type, self::VALID_TYPES, true)) {
            fwrite(STDERR, "Invalid type '$type'. Use: unit, feature, or http\n");
            return 1;
        }

        $filename = "$name.php";
        $fullPath = $this->testsPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($this->testsPath)) {
            mkdir($this->testsPath, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Test already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name, $type)) === false) {
            fwrite(STDERR, "Failed to create test: $filename\n");
            return 1;
        }

        echo "Created: tests/$filename\n";

        return 0;
    }

    /**
     * @param string $name
     * @param string $type
     *
     * @return string
     */
    private function stub(string $name, string $type): string
    {
        return match ($type) {
            'feature' => $this->featureStub($name),
            'http' => $this->httpStub($name),
            default => $this->unitStub($name),
        };
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function unitStub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Tests;

            use PHPUnit\\Framework\\TestCase;

            final class $name extends TestCase
            {
                public function test_example(): void
                {
                    \$this->assertTrue(true);
                }
            }
            PHP;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function featureStub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Tests;

            use EzPhp\\Testing\\Application\\ApplicationTestCase;

            final class $name extends ApplicationTestCase
            {
                public function test_example(): void
                {
                    \$this->assertTrue(true);
                }
            }
            PHP;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function httpStub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Tests;

            use EzPhp\\Testing\\Application\\HttpTestCase;

            final class $name extends HttpTestCase
            {
                public function test_example(): void
                {
                    \$response = \$this->get('/');
                    \$response->assertStatus(200);
                }
            }
            PHP;
    }
}
