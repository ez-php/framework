<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use PDO;
use PDOException;

/**
 * Class DoctorCommand
 *
 * Runs a health check for the application environment:
 * - PHP extension availability (required and optional)
 * - .env.example completeness against the current environment
 * - Database connectivity (if DB_* vars are configured)
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class DoctorCommand implements CommandInterface
{
    /**
     * Required PHP extensions — their absence causes a non-zero exit code.
     *
     * @var list<string>
     */
    private const array REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'intl', 'zip'];

    /**
     * Optional extensions — reported but do not affect the exit code.
     *
     * @var list<string>
     */
    private const array OPTIONAL_EXTENSIONS = ['redis', 'pcov', 'xdebug'];

    /**
     * DoctorCommand Constructor
     *
     * @param string $envExamplePath Absolute path to the .env.example file.
     */
    public function __construct(private string $envExamplePath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'doctor';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Check PHP extensions, env completeness, and database connectivity';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez doctor\n\nChecks PHP extension availability, .env completeness, and database connectivity.";
    }

    /**
     * @param list<string> $args
     *
     * @return int Exit code: 0 if all checks pass, 1 if any fail.
     */
    public function handle(array $args): int
    {
        $allPassed = true;

        echo "=== Extensions ===\n";

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            echo ($loaded ? 'OK      ' : 'MISSING ') . $ext . "\n";
            if (!$loaded) {
                $allPassed = false;
            }
        }

        foreach (self::OPTIONAL_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            echo ($loaded ? 'OK      ' : 'MISSING ') . $ext . " (optional)\n";
        }

        echo "\n=== Environment ===\n";

        if (!$this->checkEnv()) {
            $allPassed = false;
        }

        echo "\n=== Database ===\n";

        if (!$this->checkDb()) {
            $allPassed = false;
        }

        echo "\n" . ($allPassed ? "All checks passed.\n" : "Some checks failed.\n");

        return $allPassed ? 0 : 1;
    }

    /**
     * Check that all .env.example keys are present in the current environment.
     *
     * @return bool True if all keys are present or .env.example does not exist.
     */
    private function checkEnv(): bool
    {
        $keys = $this->parseEnvExampleKeys();

        if ($keys === []) {
            echo "SKIP    .env.example not found or empty\n";
            return true;
        }

        $allPresent = true;

        foreach ($keys as $key) {
            $present = array_key_exists($key, $_ENV) || getenv($key) !== false;
            echo ($present ? 'OK      ' : 'MISSING ') . $key . "\n";
            if (!$present) {
                $allPresent = false;
            }
        }

        return $allPresent;
    }

    /**
     * Try to connect to the database using DB_* environment variables.
     * Skips the check if DB_HOST, DB_DATABASE, or DB_USERNAME are not configured,
     * or if the pdo_mysql extension is not loaded.
     *
     * @return bool True if the connection succeeded or the check was skipped.
     */
    private function checkDb(): bool
    {
        if (!extension_loaded('pdo_mysql')) {
            echo "SKIP    pdo_mysql extension not loaded\n";
            return true;
        }

        $host = $this->getEnv('DB_HOST');
        $database = $this->getEnv('DB_DATABASE');
        $username = $this->getEnv('DB_USERNAME');
        $password = $this->getEnv('DB_PASSWORD') ?? '';
        $port = $this->getEnv('DB_PORT') ?? '3306';

        if ($host === null || $database === null || $username === null) {
            echo "SKIP    DB_HOST / DB_DATABASE / DB_USERNAME not configured\n";
            return true;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 3,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            echo "OK      Connected to {$host}:{$port}/{$database}\n";
            return true;
        } catch (PDOException $e) {
            echo 'FAIL    ' . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Parse required key names from the .env.example file.
     * Ignores comment lines (starting with '#') and lines without '='.
     *
     * @return list<string>
     */
    private function parseEnvExampleKeys(): array
    {
        if (!is_file($this->envExamplePath)) {
            return [];
        }

        $content = file_get_contents($this->envExamplePath);

        if ($content === false) {
            return [];
        }

        $keys = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            $key = trim(explode('=', $line, 2)[0]);

            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * Get an environment variable from $_ENV or the process environment.
     * Returns null if the variable is not set or is an empty string.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getEnv(string $key): ?string
    {
        $fromSuperGlobal = $_ENV[$key] ?? null;

        if (is_string($fromSuperGlobal) && $fromSuperGlobal !== '') {
            return $fromSuperGlobal;
        }

        $value = getenv($key);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
