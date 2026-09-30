<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use EzPhp\Maintenance\MaintenanceMode;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class MaintenanceModeTest
 *
 * @package Tests\Maintenance
 */
#[CoversClass(MaintenanceMode::class)]
final class MaintenanceModeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/ez-maint-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/framework/down');
        @rmdir($this->dir . '/framework');
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function mode(): MaintenanceMode
    {
        return new MaintenanceMode($this->dir . '/framework/down');
    }

    public function test_is_up_until_activated(): void
    {
        self::assertFalse($this->mode()->isDown());
        self::assertFalse($this->mode()->deactivate());
    }

    public function test_activate_creates_the_directory_and_stores_settings(): void
    {
        $this->mode()->activate(retryAfter: 120, secret: 's3cret');

        $mode = $this->mode(); // a fresh instance, as another worker would see it
        self::assertTrue($mode->isDown());
        self::assertSame(120, $mode->retryAfter());
        self::assertSame('s3cret', $mode->secret());
        self::assertSame(hash_hmac('sha256', 'ez-maintenance-bypass', 's3cret'), $mode->bypassToken());
    }

    public function test_without_options_there_is_no_retry_or_bypass(): void
    {
        $this->mode()->activate();

        self::assertNull($this->mode()->retryAfter());
        self::assertNull($this->mode()->secret());
        self::assertNull($this->mode()->bypassToken());
    }

    public function test_deactivate_brings_the_application_back_up(): void
    {
        $this->mode()->activate();

        self::assertTrue($this->mode()->deactivate());
        self::assertFalse($this->mode()->isDown());
    }

    public function test_a_corrupt_marker_still_means_down(): void
    {
        mkdir($this->dir . '/framework', 0o755, true);
        file_put_contents($this->dir . '/framework/down', 'not json');

        self::assertTrue($this->mode()->isDown());
        self::assertNull($this->mode()->retryAfter());
    }
}
