<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * A fourteen-day pay period that can start on any day of the week, as set
 * in the settings. The tutor is paid on the day after it ends.
 */
final readonly class PayPeriod
{
    public const LENGTH_IN_DAYS = 14;

    public CarbonImmutable $start;

    public CarbonImmutable $end;

    public function __construct(CarbonInterface $start)
    {
        $this->start = CarbonImmutable::instance($start)->startOfDay();
        $this->end = $this->start->addDays(self::LENGTH_IN_DAYS - 1);
    }

    /**
     * Find the pay period containing the given date, using an anchor date
     * that is known to begin a pay period.
     */
    public static function containing(CarbonInterface $date, CarbonInterface $anchor): self
    {
        $date = CarbonImmutable::instance($date)->startOfDay();
        $anchor = CarbonImmutable::instance($anchor)->startOfDay();

        $daysFromAnchor = (int) $anchor->diffInDays($date, false);
        $periodsFromAnchor = intdiv($daysFromAnchor, self::LENGTH_IN_DAYS);

        if ($daysFromAnchor < 0 && $daysFromAnchor % self::LENGTH_IN_DAYS !== 0) {
            $periodsFromAnchor--;
        }

        return new self($anchor->addDays($periodsFromAnchor * self::LENGTH_IN_DAYS));
    }

    /**
     * The day the tutor is paid for this period: the day after it ends.
     */
    public function payday(): CarbonImmutable
    {
        return $this->end->addDay();
    }

    public function previous(): self
    {
        return new self($this->start->subDays(self::LENGTH_IN_DAYS));
    }

    public function next(): self
    {
        return new self($this->start->addDays(self::LENGTH_IN_DAYS));
    }

    public function contains(CarbonInterface $date): bool
    {
        return CarbonImmutable::instance($date)->startOfDay()->between($this->start, $this->end);
    }

    /**
     * Every date in the period, in order.
     *
     * @return list<CarbonImmutable>
     */
    public function dates(): array
    {
        return array_values(array_map(
            fn (CarbonInterface $date): CarbonImmutable => CarbonImmutable::instance($date),
            iterator_to_array(CarbonPeriod::create($this->start, $this->end)),
        ));
    }
}
