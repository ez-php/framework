<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Output;
use EzPhp\Maintenance\MaintenanceMode;

/**
 * Class UpCommand
 *
 * Brings the application out of maintenance mode (`ez down`).
 *
 * Usage:
 *   ez up
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class UpCommand implements CommandInterface
{
    /**
     * UpCommand Constructor
     *
     * @param MaintenanceMode $maintenance
     * @param resource|null $errorStream Where error messages go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private MaintenanceMode $maintenance,
        private mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'up';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Bring the application out of maintenance mode';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez up\n\nRemoves the maintenance marker written by `ez down`.";
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        if (!$this->maintenance->isDown()) {
            Output::line('Application is not in maintenance mode.');

            return 0;
        }

        if (!$this->maintenance->deactivate()) {
            Output::error('Could not remove the maintenance marker.', $this->errorStream);

            return 1;
        }

        Output::success('Application is live again.');

        return 0;
    }
}
