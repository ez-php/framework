<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;

/**
 * Class MakeMigrationCommand
 *
 * @package EzPhp\Console\Command
 */
final class MakeMigrationCommand implements CommandInterface
{
    /**
     * MakeMigrationCommand Constructor
     *
     * @param string $migrationsPath
     */
    public function __construct(private readonly string $migrationsPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:migration';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new migration file';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:migration <name> [--force]';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);
        $name = $input->argument(0);

        if ($name === null) {
            fwrite(STDERR, "Usage: ez make:migration <name> [--force]\n");
            return 1;
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_$name.php";
        $fullPath = $this->migrationsPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0o755, true);
        }

        if (file_exists($fullPath) && !$input->hasFlag('force')) {
            fwrite(STDERR, "Migration already exists: $filename (use --force to overwrite)\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub()) === false) {
            fwrite(STDERR, "Failed to create migration: $filename\n");
            return 1;
        }

        echo "Created: $filename\n";

        return 0;
    }

    /**
     * @return string
     */
    private function stub(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            use EzPhp\Migration\MigrationInterface;

            return new class implements MigrationInterface {
                public function up(\PDO $pdo): void
                {
                    // $pdo->exec('CREATE TABLE example (id INT AUTO_INCREMENT PRIMARY KEY)');
                }

                public function down(\PDO $pdo): void
                {
                    // $pdo->exec('DROP TABLE IF EXISTS example');
                }
            };
            PHP;
    }
}
