<?php

declare(strict_types=1);

namespace Tests\Application;

use EzPhp\Application\StaticFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Tests\TestCase;

/**
 * Class StaticFacadeTest
 *
 * @package Tests\Application
 */
#[CoversClass(StaticFacade::class)]
final class StaticFacadeTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        FacadeA::resetInstance();
        FacadeB::resetInstance();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        FacadeA::resetInstance();
        FacadeB::resetInstance();
        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_set_and_get_instance(): void
    {
        $service = new FacadeServiceA();
        FacadeA::setInstance($service);

        $this->assertSame($service, FacadeA::getInstance());
    }

    /**
     * @return void
     */
    public function test_get_instance_throws_when_not_set(): void
    {
        $this->expectException(RuntimeException::class);
        FacadeA::getInstance();
    }

    /**
     * @return void
     */
    public function test_reset_instance_clears_stored_instance(): void
    {
        FacadeA::setInstance(new FacadeServiceA());
        FacadeA::resetInstance();

        $this->expectException(RuntimeException::class);
        FacadeA::getInstance();
    }

    /**
     * @return void
     */
    public function test_separate_subclasses_have_separate_instances(): void
    {
        $serviceA = new FacadeServiceA();
        $serviceB = new FacadeServiceB();

        FacadeA::setInstance($serviceA);
        FacadeB::setInstance($serviceB);

        $this->assertSame($serviceA, FacadeA::getInstance());
        $this->assertSame($serviceB, FacadeB::getInstance());
    }

    /**
     * @return void
     */
    public function test_reset_one_facade_does_not_affect_another(): void
    {
        $serviceA = new FacadeServiceA();
        $serviceB = new FacadeServiceB();

        FacadeA::setInstance($serviceA);
        FacadeB::setInstance($serviceB);

        FacadeA::resetInstance();

        $this->assertSame($serviceB, FacadeB::getInstance());
    }
}

/**
 * Class FacadeServiceA
 */
class FacadeServiceA
{
}

/**
 * Class FacadeServiceB
 */
class FacadeServiceB
{
}

/**
 * Class FacadeA
 */
final class FacadeA extends StaticFacade
{
    /** @var FacadeServiceA|null */
    protected static object|null $instance = null;
}

/**
 * Class FacadeB
 */
final class FacadeB extends StaticFacade
{
    /** @var FacadeServiceB|null */
    protected static object|null $instance = null;
}
