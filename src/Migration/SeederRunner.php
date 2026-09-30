<?php

declare(strict_types=1);

namespace EzPhp\Migration;

use EzPhp\Contracts\DatabaseInterface;

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
     * @param DatabaseInterface $db Passed straight through to SeederInterface::run().
     * @param string   $path  Path to the seeders directory (e.g. database/seeders).
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $path,
    ) {
    }

    /**
     * Run all seeder files in alphabetical order.
     * When $file is given only that single file is run.
     *
     * $db:setup is not atomic across the whole seed step — there is no umbrella
     * transaction, so a seeder that throws partway through leaves every prior
     * seeder's writes committed. $onSeeded, when given, is invoked with each
     * basename immediately after that seeder succeeds (not batched at the end),
     * so a caller can report exactly which seeders completed before a failure.
     *
     * @param string|null              $file
     * @param (callable(string): void)|null $onSeeded  Called after each seeder succeeds.
     *
     * @return list<string>  Basenames of the seeders that were executed.
     */
    public function run(?string $file = null, ?callable $onSeeded = null): array
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

            if ($onSeeded !== null) {
                $onSeeded($basename);
            }
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
