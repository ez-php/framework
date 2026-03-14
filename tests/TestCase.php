<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Class TestCase
 *
 * @package Tests
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Bootstrapping for your framework can go here
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        // Cleanup after each test

        parent::tearDown();
    }
}
