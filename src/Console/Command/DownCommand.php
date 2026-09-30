<?php

declare(strict_types=1);

namespace EzPhp\Console\Command;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;
use EzPhp\Console\Output;
use EzPhp\Maintenance\MaintenanceMode;

/**
 * Class DownCommand
 *
 * Puts the application into maintenance mode: `MaintenanceModeMiddleware`
 * answers every request with 503 until `ez up`.
 *
 * Usage:
 *   ez down [--retry=60] [--secret=<bypass-secret>]
 *
 * @internal
 * @package EzPhp\Console\Command
 */
final readonly class DownCommand implements CommandInterface
{
    /**
     * DownCommand Constructor
     *
     * @param MaintenanceMode $maintenance
     */
    public function __construct(private MaintenanceMode $maintenance)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'down';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Put the application into maintenance mode';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return "Usage: ez down [--retry=<seconds>] [--secret=<secret>]\n\n"
            . "Requests get 503 Service Unavailable until `ez up`. Requires MaintenanceModeMiddleware in the\n"
            . "global middleware stack.\n\n"
            . "  --retry   Seconds sent as the Retry-After header.\n"
            . '  --secret  Visiting /<secret> sets a cookie that bypasses maintenance mode.';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $input = new Input($args);
        $retry = $input->option('retry');
        $secret = $input->option('secret');

        if ($retry !== '' && preg_match('/^[1-9][0-9]*$/', $retry) !== 1) {
            Output::error('--retry must be a positive number of seconds.');

            return 1;
        }

        if ($secret !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $secret) !== 1) {
            Output::error('--secret may only contain letters, digits, "-" and "_".');

            return 1;
        }

        try {
            $this->maintenance->activate($retry === '' ? null : (int) $retry, $secret === '' ? null : $secret);
        } catch (\RuntimeException $e) {
            Output::error($e->getMessage());

            return 1;
        }

        Output::success('Application is now in maintenance mode.');

        if ($secret !== '') {
            Output::line("Bypass: visit /{$secret} to get a cookie that lets you through.");
        }

        return 0;
    }
}
