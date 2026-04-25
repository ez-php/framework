<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;

/**
 * Class MakeSeederCommand
 *
 * Scaffolds a seeder stub in database/seeders/.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeSeederCommand implements CommandInterface
{
    /**
     * MakeSeederCommand Constructor
     *
     * @param string $seedersPath
     */
    public function __construct(private string $seedersPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:seeder';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new seeder file';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:seeder <name>';
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
            fwrite(STDERR, "Usage: ez make:seeder <name>\n");
            return 1;
        }

        // Ensure the name ends with .php
        $filename = str_ends_with($name, '.php') ? $name : $name . '.php';
        $fullPath = $this->seedersPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($this->seedersPath)) {
            mkdir($this->seedersPath, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Seeder already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub()) === false) {
            fwrite(STDERR, "Failed to create seeder: $filename\n");
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

            use EzPhp\Database\Database;
            use EzPhp\Migration\SeederInterface;

            return new class implements SeederInterface {
                public function run(Database $db): void
                {
                    // $db->execute('INSERT INTO table (col) VALUES (?)', ['value']);
                }
            };
            PHP;
    }
}
