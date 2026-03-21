<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Config\ConfigLoader;
use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Exceptions\ConfigException;

/**
 * Class ConfigCacheCommand
 *
 * Serializes all configuration files into a single PHP file so that the
 * application does not need to scan and require multiple config files on
 * every request in production.
 *
 * Usage:
 *   ez config:cache
 *
 * The cache is written to `bootstrap/cache/config.php` relative to the
 * application root. The directory is created automatically if absent.
 *
 * @package EzPhp\Console\Command
 */
final class ConfigCacheCommand implements CommandInterface
{
    /**
     * ConfigCacheCommand Constructor
     *
     * @param ConfigLoader $loader    Config loader used to gather all configuration values.
     * @param string       $cachePath Absolute path to the cache file.
     */
    public function __construct(
        private readonly ConfigLoader $loader,
        private readonly string $cachePath,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'config:cache';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Cache all configuration files into a single compiled file';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez config:cache\n\nLoads all config/*.php files and writes a single serialized PHP file to\nbootstrap/cache/config.php. Delete it with config:clear.";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        try {
            $config = $this->loader->load();
        } catch (ConfigException $e) {
            Output::error('Failed to load config: ' . $e->getMessage());

            return 1;
        }

        $dir = dirname($this->cachePath);

        if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
            Output::error("Could not create cache directory: $dir");

            return 1;
        }

        $content = '<?php return ' . var_export($config, true) . ';' . "\n";

        if (file_put_contents($this->cachePath, $content) === false) {
            Output::error("Could not write config cache: {$this->cachePath}");

            return 1;
        }

        Output::success('Configuration cached successfully.');

        return 0;
    }
}
