<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;

/**
 * Class EnvCheckCommand
 *
 * Reads .env.example and checks that every required key is present in the
 * actual environment. Prints OK/MISSING for each key and returns exit code 1
 * if any key is missing, 0 if all are present.
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class EnvCheckCommand implements CommandInterface
{
    /**
     * EnvCheckCommand Constructor
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
        return 'env:check';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Check that all required .env.example keys are present in the environment';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez env:check\n\nChecks .env.example for required keys and reports OK/MISSING for each.";
    }

    /**
     * @param list<string> $args
     *
     * @return int Exit code: 0 if all keys present, 1 if any missing.
     */
    public function handle(array $args): int
    {
        $keys = $this->parseRequiredKeys();
        $allPresent = true;

        foreach ($keys as $key) {
            $envValue = getenv($key);
            $present = array_key_exists($key, $_ENV) || $envValue !== false;

            if ($present) {
                echo "OK      $key\n";
            } else {
                echo "MISSING $key\n";
                $allPresent = false;
            }
        }

        return $allPresent ? 0 : 1;
    }

    /**
     * Parse the .env.example file and return a list of required key names.
     * Lines starting with '#' or that don't contain '=' are ignored.
     *
     * @return list<string>
     */
    private function parseRequiredKeys(): array
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
}
