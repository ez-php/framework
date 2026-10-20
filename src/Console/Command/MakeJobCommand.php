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
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private string $appPath,
        private mixed $errorStream = null,
    ) {
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
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:job <ClassName>\n");
            return 1;
        }

        $dir = $this->appPath . DIRECTORY_SEPARATOR . 'Jobs';
        $filename = "$name.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Job already exists: $filename\n");
            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create job: $filename\n");
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

            /**
             * Class $name
             *
             * @package App\\Jobs
             */
            final class $name implements JobInterface
            {
                private int \$attempts = 0;

                /**
                 * $name Constructor
                 *
                 * @param string \$queue    Queue name the job is pushed to
                 * @param int    \$delay    Seconds before the job becomes available
                 * @param int    \$maxTries Attempts before the job is marked as failed
                 */
                public function __construct(
                    private string \$queue = 'default',
                    private int \$delay = 0,
                    private int \$maxTries = 3,
                ) {
                }

                /**
                 * Execute the job.
                 *
                 * @return void
                 */
                public function handle(): void
                {
                    // Implement the job's work here.
                }

                /**
                 * Called once the job has failed permanently.
                 *
                 * @param Throwable \$e
                 *
                 * @return void
                 */
                public function fail(Throwable \$e): void
                {
                    // Handle permanent failure here (e.g. log or notify).
                }

                /**
                 * @return string
                 */
                public function getQueue(): string
                {
                    return \$this->queue;
                }

                /**
                 * @return int
                 */
                public function getDelay(): int
                {
                    return \$this->delay;
                }

                /**
                 * @return int
                 */
                public function getMaxTries(): int
                {
                    return \$this->maxTries;
                }

                /**
                 * @return int
                 */
                public function getAttempts(): int
                {
                    return \$this->attempts;
                }

                /**
                 * @return void
                 */
                public function incrementAttempts(): void
                {
                    \$this->attempts++;
                }
            }
            PHP;
    }
}
