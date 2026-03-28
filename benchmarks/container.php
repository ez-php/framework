<?php

declare(strict_types=1);

/**
 * Performance benchmark for EzPhp\Container\Container.
 *
 * Measures the overhead of binding and resolving services through
 * the DI container, including autowiring and singleton caching.
 *
 * Exits with code 1 if the per-resolve time exceeds the defined threshold,
 * allowing CI to detect performance regressions automatically.
 *
 * Usage:
 *   php benchmarks/container.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use EzPhp\Container\Container;

const ITERATIONS = 1000;
const THRESHOLD_MS = 5.0; // per-resolve upper bound in milliseconds

// ── Sample classes for autowiring ────────────────────────────────────────────

class BenchServiceA
{
    public function __construct()
    {
    }
}

class BenchServiceB
{
    public function __construct(public readonly BenchServiceA $a)
    {
    }
}

class BenchServiceC
{
    public function __construct(
        public readonly BenchServiceA $a,
        public readonly BenchServiceB $b,
    ) {
    }
}

// ── Benchmark ─────────────────────────────────────────────────────────────────

$container = new Container();

// Warm-up: one pass to allow opcode caching before measuring
$container->make(BenchServiceC::class);

$start = hrtime(true);

for ($i = 0; $i < ITERATIONS; $i++) {
    // Fresh container per iteration so singletons don't trivialise the bench
    $c = new Container();
    $c->make(BenchServiceC::class);
}

$end = hrtime(true);

$totalMs = ($end - $start) / 1_000_000;
$perResolve = $totalMs / ITERATIONS;

echo sprintf(
    "Container Resolution Benchmark\n" .
    "  Depth                : 3 levels (C → B → A)\n" .
    "  Iterations           : %d\n" .
    "  Total time           : %.2f ms\n" .
    "  Per resolve          : %.3f ms\n" .
    "  Threshold            : %.1f ms\n",
    ITERATIONS,
    $totalMs,
    $perResolve,
    THRESHOLD_MS,
);

if ($perResolve > THRESHOLD_MS) {
    echo sprintf(
        "FAIL: %.3f ms exceeds threshold of %.1f ms\n",
        $perResolve,
        THRESHOLD_MS,
    );
    exit(1);
}

echo "PASS\n";
exit(0);
