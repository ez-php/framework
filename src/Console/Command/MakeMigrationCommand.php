<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;

/**
 * Class MakeMigrationCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeMigrationCommand implements CommandInterface
{
    /**
     * MakeMigrationCommand Constructor
     *
     * @param string $migrationsPath
     */
    public function __construct(private string $migrationsPath)
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

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create migration: $filename\n");
            return 1;
        }

        echo "Created: $filename\n";

        return 0;
    }

    /**
     * @param string $name Migration name (e.g. create_users_table)
     *
     * @return string
     */
    private function stub(string $name): string
    {
        if (preg_match('/^create_(.+)_table$/', $name, $m)) {
            return $this->createTableStub($m[1]);
        }

        if (preg_match('/^add_.+_to_(.+)_table$/', $name, $m)) {
            return $this->addColumnsStub($m[1]);
        }

        if (preg_match('/^drop_(.+)_table$/', $name, $m)) {
            return $this->dropTableStub($m[1]);
        }

        return $this->blankStub();
    }

    /**
     * @param string $table
     *
     * @return string
     */
    private function createTableStub(string $table): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            use EzPhp\\Contracts\\Schema\\SchemaInterface;
            use EzPhp\\Migration\\MigrationInterface;
            use EzPhp\\Orm\\Schema\\Blueprint;

            return new class implements MigrationInterface {
                public function up(SchemaInterface \$schema): void
                {
                    \$schema->create('$table', function (Blueprint \$table): void {
                        \$table->id();
                        \$table->timestamps();
                    });
                }

                public function down(SchemaInterface \$schema): void
                {
                    \$schema->dropIfExists('$table');
                }
            };
            PHP;
    }

    /**
     * @param string $table
     *
     * @return string
     */
    private function addColumnsStub(string $table): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            use EzPhp\\Contracts\\Schema\\SchemaInterface;
            use EzPhp\\Migration\\MigrationInterface;
            use EzPhp\\Orm\\Schema\\Blueprint;

            return new class implements MigrationInterface {
                public function up(SchemaInterface \$schema): void
                {
                    \$schema->table('$table', function (Blueprint \$table): void {
                        \$table->string('column_name');
                    });
                }

                public function down(SchemaInterface \$schema): void
                {
                    \$schema->table('$table', function (Blueprint \$table): void {
                        \$table->dropColumn('column_name');
                    });
                }
            };
            PHP;
    }

    /**
     * @param string $table
     *
     * @return string
     */
    private function dropTableStub(string $table): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            use EzPhp\\Contracts\\Schema\\SchemaInterface;
            use EzPhp\\Migration\\MigrationInterface;

            return new class implements MigrationInterface {
                public function up(SchemaInterface \$schema): void
                {
                    \$schema->drop('$table');
                }

                public function down(SchemaInterface \$schema): void
                {
                    // \$schema->create('$table', function (\\EzPhp\\Orm\\Schema\\Blueprint \$table): void {
                    //     \$table->id();
                    //     \$table->timestamps();
                    // });
                }
            };
            PHP;
    }

    /**
     * @return string
     */
    private function blankStub(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            use EzPhp\Contracts\Schema\SchemaInterface;
            use EzPhp\Migration\MigrationInterface;

            return new class implements MigrationInterface {
                public function up(SchemaInterface $schema): void
                {
                    //
                }

                public function down(SchemaInterface $schema): void
                {
                    //
                }
            };
            PHP;
    }
}
