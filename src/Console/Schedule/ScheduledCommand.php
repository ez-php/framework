<?php

declare(strict_types=1);

namespace EzPhp\Console\Schedule;

use DateTimeInterface;

/**
 * Class ScheduledCommand
 *
 * Represents a single entry in the Scheduler: a command name paired with
 * a frequency predicate. Frequency methods return $this for fluent chaining.
 *
 * Design note: the class is intentionally mutable so that the caller can
 * attach a frequency after receiving the instance from Scheduler::command().
 *
 * @package EzPhp\Console\Schedule
 */
final class ScheduledCommand
{
    /**
     * Closure that accepts a DateTimeInterface and returns true when the command is due.
     *
     * @var \Closure(DateTimeInterface): bool
     */
    private \Closure $duePredicate;

    private string $frequencyDescription;

    /**
     * ScheduledCommand Constructor
     *
     * @param string $name Console command name as registered in the Console (e.g. 'migrate').
     */
    public function __construct(private readonly string $name)
    {
        // Default: never due until a frequency method is called.
        $this->duePredicate = static fn (DateTimeInterface $t): bool => false;
        $this->frequencyDescription = 'not scheduled';
    }

    /**
     * Return the command name.
     *
     * @return string
     */
    public function getCommand(): string
    {
        return $this->name;
    }

    /**
     * Return a human-readable description of the configured frequency.
     *
     * @return string
     */
    public function getFrequencyDescription(): string
    {
        return $this->frequencyDescription;
    }

    /**
     * Schedule the command to run every minute.
     *
     * @return $this
     */
    public function everyMinute(): self
    {
        $this->duePredicate = static fn (DateTimeInterface $t): bool => true;
        $this->frequencyDescription = 'every minute';

        return $this;
    }

    /**
     * Schedule the command to run once per hour (at minute 0).
     *
     * @return $this
     */
    public function hourly(): self
    {
        $this->duePredicate = static fn (DateTimeInterface $t): bool => (int) $t->format('i') === 0;
        $this->frequencyDescription = 'every hour';

        return $this;
    }

    /**
     * Schedule the command to run once per day at midnight (00:00).
     *
     * @return $this
     */
    public function daily(): self
    {
        $this->duePredicate = static fn (DateTimeInterface $t): bool => $t->format('Hi') === '0000';
        $this->frequencyDescription = 'daily at midnight';

        return $this;
    }

    /**
     * Schedule the command to run once per week on Sunday at midnight.
     *
     * @return $this
     */
    public function weekly(): self
    {
        // w=0 (Sunday), Hi=0000
        $this->duePredicate = static fn (DateTimeInterface $t): bool => $t->format('wHi') === '00000';
        $this->frequencyDescription = 'weekly on Sunday at midnight';

        return $this;
    }

    /**
     * Schedule the command to run once per month on the 1st at midnight.
     *
     * @return $this
     */
    public function monthly(): self
    {
        // d=01, Hi=0000
        $this->duePredicate = static fn (DateTimeInterface $t): bool => $t->format('dHi') === '010000';
        $this->frequencyDescription = 'monthly on the 1st at midnight';

        return $this;
    }

    /**
     * Check whether this command is due at the given time.
     *
     * @internal Called by Scheduler::dueCommands(); not part of the public scheduled command API.
     *
     * @param DateTimeInterface $time The time to check against (typically the current time).
     *
     * @return bool
     */
    public function isDue(DateTimeInterface $time): bool
    {
        return ($this->duePredicate)($time);
    }

    /**
     * Find the next time this command will be due, starting from the given moment.
     *
     * Probes up to 7 days (10 080 minute-steps) forward. Returns null when
     * no due time is found within that window (e.g. the entry has no frequency set).
     *
     * @param DateTimeInterface $after Starting point for the search (exclusive).
     *
     * @return \DateTimeImmutable|null
     */
    public function nextRunAfter(DateTimeInterface $after): ?\DateTimeImmutable
    {
        $base = \DateTimeImmutable::createFromInterface($after)
            ->setTime((int) $after->format('H'), (int) $after->format('i'), 0);

        for ($i = 1; $i <= 10080; $i++) {
            $candidate = $base->modify("+{$i} minutes");

            if ($this->isDue($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
