<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeJobCommand
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class MakeJobCommand implements CommandInterface
{
    /**
     * MakeJobCommand Constructor
     *
     * @param string $appPath
     */
    public function __construct(private string $appPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:job';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new job class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:job <ClassName>';
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
            fwrite(STDERR, "Usage: ez make:job <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Jobs';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Job already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite(STDERR, "Failed to create job: $filename\n");
            return 1;
        }

        echo "Created: app/Jobs/$filename\n";

        return 0;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Jobs;

            use EzPhp\\Contracts\\JobInterface;
            use Throwable;

            final class $name implements JobInterface
            {
                private int \$attempts = 0;

                public function handle(): void
                {
                    // TODO: implement job logic
                }

                public function fail(Throwable \$e): void
                {
                    // TODO: handle permanent failure
                }

                public function getAttempts(): int
                {
                    return \$this->attempts;
                }

                public function incrementAttempts(): void
                {
                    \$this->attempts++;
                }

                public function getMaxAttempts(): int
                {
                    return 3;
                }
            }
            PHP;
    }
}
