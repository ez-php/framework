<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeFactoryCommand
 *
 * Scaffolds a test factory for an entity: a class that builds the
 * ez-php/testing EntityFactory with the entity's default attributes. Lives in
 * the framework, not in the dev-only ez-php/testing, so `provider/modules.php`
 * stays valid on a production install without require-dev.
 *
 * Usage:
 *   ez make:factory User   — writes tests/Factories/UserFactory.php
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeFactoryCommand implements CommandInterface
{
    /**
     * MakeFactoryCommand Constructor
     *
     * @param string $factoriesPath Absolute path to the tests/Factories/ directory.
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private string $factoriesPath,
        private mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:factory';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new test factory for an entity';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:factory <EntityName>';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $entity = $args[0] ?? null;

        if ($entity === null || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $entity)) {
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:factory <EntityName>\n");
            return 1;
        }

        $filename = "{$entity}Factory.php";
        $fullPath = $this->factoriesPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($this->factoriesPath)) {
            mkdir($this->factoriesPath, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Factory already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($entity)) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create factory: $filename\n");
            return 1;
        }

        echo "Created: tests/Factories/$filename\n";

        return 0;
    }

    /**
     * @param string $entity
     *
     * @return string
     */
    private function stub(string $entity): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Tests\\Factories;

            use App\\Entities\\{$entity};
            use App\\Repositories\\{$entity}Repository;
            use EzPhp\\Testing\\EntityFactory;

            /**
             * Class {$entity}Factory
             *
             * @package Tests\\Factories
             */
            final class {$entity}Factory
            {
                /**
                 * @param {$entity}Repository \$repository
                 *
                 * @return EntityFactory<{$entity}>
                 */
                public static function new({$entity}Repository \$repository): EntityFactory
                {
                    return new EntityFactory({$entity}::class, \$repository, [
                        // 'name' => 'Default name',
                    ]);
                }
            }

            PHP;
    }
}
