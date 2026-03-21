<?php

declare(strict_types=1);

namespace EzPhp\Config;

use EzPhp\Exceptions\ConfigException;

/**
 * Class ConfigLoader
 *
 * @package EzPhp\Config
 */
final readonly class ConfigLoader
{
    /**
     * ConfigLoader Constructor
     *
     * @param string|null $configPath
     */
    public function __construct(private ?string $configPath = null)
    {
    }

    /**
     * @return array<string, mixed>
     * @throws ConfigException
     */
    public function load(): array
    {
        $configPath = $this->configPath ?? __DIR__ . '/../../config';
        if (!is_dir($configPath)) {
            throw new ConfigException('Config directory not found!');
        }

        $files = glob($configPath . '/*.php');
        if ($files === false) {
            throw new ConfigException('Failed to read config directory.');
        }

        $config = [];
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $data = require $file;

            if (is_array($data)) {
                $config[$key] = $data;
            }
        }

        return $config;
    }
}
