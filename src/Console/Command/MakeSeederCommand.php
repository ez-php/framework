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
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private string $seedersPath,
        private mixed $errorStream = null,
    ) {
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
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:seeder <name>\n");
            return 1;
        }

        $base = str_ends_with($name, '.php') ? substr($name, 0, -4) : $name;

        // Letters, digits and underscores only — no path separators, so the file
        // always lands in the seeders directory. A leading digit is allowed because
        // SeederRunner orders files by name (e.g. 01_users).
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_]*$/', $base)) {
            fwrite($this->errorStream ?? STDERR, "Invalid seeder name: $name (letters, digits and underscores only)\n");
            return 1;
        }

        $filename = $base . '.php';
        $fullPath = $this->seedersPath . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($this->seedersPath)) {
            mkdir($this->seedersPath, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Seeder already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub()) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create seeder: $filename\n");
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

            use EzPhp\Contracts\DatabaseInterface;
            use EzPhp\Migration\SeederInterface;

            return new class implements SeederInterface {
                public function run(DatabaseInterface $db): void
                {
                    // $db->execute('INSERT INTO table (col) VALUES (?)', ['value']);
                }
            };
            PHP;
    }
}
