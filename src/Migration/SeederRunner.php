<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Database\Database;

/**
 * Class SeederRunner
 *
 * Discovers and executes seeder files from database/seeders/.
 * Each file must return an anonymous class implementing SeederInterface.
 *
 * @internal
 * @package EzPhp\Migration
 */
final class SeederRunner
{
    /**
     * SeederRunner Constructor
     *
     * @param Database $db
     * @param string   $path  Path to the seeders directory (e.g. database/seeders).
     */
    public function __construct(
        private readonly Database $db,
        private readonly string $path,
    ) {
    }

    /**
     * Run all seeder files in alphabetical order.
     * When $file is given only that single file is run.
     *
     * @param string|null $file  Basename of the seeder file (e.g. 'UserSeeder.php').
     *
     * @return list<string>  Basenames of the seeders that were executed.
     */
    public function run(?string $file = null): array
    {
        $files = $this->getFiles();

        if ($files === []) {
            return [];
        }

        if ($file !== null) {
            $files = in_array($file, $files, true) ? [$file] : [];
        }

        $ran = [];

        foreach ($files as $basename) {
            $seeder = $this->load($basename);
            $seeder->run($this->db);
            $ran[] = $basename;
        }

        return $ran;
    }

    /**
     * @return list<string>
     */
    public function getFiles(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $files = glob($this->path . DIRECTORY_SEPARATOR . '*.php') ?: [];
        $files = array_map('basename', $files);
        sort($files);

        return $files;
    }

    /**
     * @param string $basename
     *
     * @return SeederInterface
     */
    private function load(string $basename): SeederInterface
    {
        /** @var SeederInterface $instance */
        $instance = require $this->path . DIRECTORY_SEPARATOR . $basename;

        return $instance;
    }
}
