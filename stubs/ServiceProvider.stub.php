<?php

declare(strict_types=1);

namespace EzPhp\Contracts;

/**
 * PHPStan stub — provides the type signatures added in ez-php/contracts 0.6.1
 * until the framework's composer.lock is updated to reference that release.
 */
abstract class ServiceProvider
{
    /**
     * @return bool
     */
    public function deferred(): bool
    {
    }

    /**
     * @return list<string>
     */
    public function provides(): array
    {
    }
}
