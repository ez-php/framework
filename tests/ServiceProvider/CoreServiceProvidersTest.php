<?php

declare(strict_types=1);

namespace Tests\ServiceProvider;

use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Routing\RouterServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class CoreServiceProvidersTest
 *
 * @package Tests\ServiceProvider
 */
#[CoversClass(CoreServiceProviders::class)]
final class CoreServiceProvidersTest extends TestCase
{
    /**
     * @return void
     */
    public function test_all_returns_non_empty_list(): void
    {
        $providers = CoreServiceProviders::all();

        $this->assertNotEmpty($providers);
    }

    /**
     * @return void
     */
    public function test_all_contains_core_providers(): void
    {
        $providers = CoreServiceProviders::all();

        $this->assertContains(ConfigServiceProvider::class, $providers);
        $this->assertContains(DatabaseServiceProvider::class, $providers);
        $this->assertContains(MigrationServiceProvider::class, $providers);
        $this->assertContains(RouterServiceProvider::class, $providers);
        $this->assertContains(ExceptionHandlerServiceProvider::class, $providers);
        $this->assertContains(ConsoleServiceProvider::class, $providers);
    }

    /**
     * @return void
     */
    public function test_orm_providers_not_in_core(): void
    {
        $providers = CoreServiceProviders::all();

        // ORM and Schema providers are in the orm module, not the framework core
        $this->assertNotContains('EzPhp\\Orm\\ModelServiceProvider', $providers);
        $this->assertNotContains('EzPhp\\Orm\\Schema\\SchemaServiceProvider', $providers);
    }
}
