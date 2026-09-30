<?php

declare(strict_types=1);

namespace EzPhp\Maintenance;

/**
 * Class MaintenanceMode
 *
 * The on/off switch behind `ez down` / `ez up`: a JSON marker file (by default
 * `storage/framework/down`) that exists while the application is down. Being a
 * file, it is shared by every PHP worker on the host and survives restarts;
 * no cache or database is needed during a deploy.
 *
 * @package EzPhp\Maintenance
 */
final readonly class MaintenanceMode
{
    /**
     * Name of the cookie that lets a holder of the secret bypass maintenance mode.
     */
    public const string BYPASS_COOKIE = 'ez_maintenance_bypass';

    /**
     * MaintenanceMode Constructor
     *
     * @param string $markerFile Absolute path of the marker file.
     */
    public function __construct(private string $markerFile)
    {
    }

    /**
     * @return bool
     */
    public function isDown(): bool
    {
        return is_file($this->markerFile);
    }

    /**
     * Take the application down.
     *
     * @param int|null    $retryAfter Seconds sent as `Retry-After`; null omits the header.
     * @param string|null $secret     Visiting `/<secret>` sets a bypass cookie; null = no bypass.
     *
     * @throws \RuntimeException When the marker file cannot be written.
     *
     * @return void
     */
    public function activate(?int $retryAfter = null, ?string $secret = null): void
    {
        $dir = dirname($this->markerFile);

        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }

        $payload = json_encode(['since' => time(), 'retry' => $retryAfter, 'secret' => $secret], JSON_THROW_ON_ERROR);

        if (file_put_contents($this->markerFile, $payload, LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write maintenance marker: {$this->markerFile}");
        }
    }

    /**
     * Bring the application back up.
     *
     * @return bool False when it was not down.
     */
    public function deactivate(): bool
    {
        return is_file($this->markerFile) && unlink($this->markerFile);
    }

    /**
     * The active maintenance settings, read from the marker file in one go — or
     * null while the application is up. A marker that cannot be read or decoded
     * still means "down", with no retry and no bypass.
     *
     * @return MaintenanceState|null
     */
    public function state(): ?MaintenanceState
    {
        if (!is_file($this->markerFile)) {
            return null;
        }

        $raw = @file_get_contents($this->markerFile);
        $data = $raw === false ? null : json_decode($raw, true);
        $data = is_array($data) ? $data : [];

        $retry = $data['retry'] ?? null;
        $secret = $data['secret'] ?? null;

        return new MaintenanceState(
            is_int($retry) && $retry > 0 ? $retry : null,
            is_string($secret) && $secret !== '' ? $secret : null,
        );
    }

    /**
     * @return int|null
     */
    public function retryAfter(): ?int
    {
        return $this->state()?->retryAfter;
    }

    /**
     * @return string|null
     */
    public function secret(): ?string
    {
        return $this->state()?->secret;
    }

    /**
     * The bypass cookie value for the current secret — see MaintenanceState::bypassToken().
     *
     * @return string|null Null when no secret is set.
     */
    public function bypassToken(): ?string
    {
        return $this->state()?->bypassToken();
    }
}
