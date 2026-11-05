<?php

namespace Tests\Unit;

use App\Support\PayPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayPeriodTest extends TestCase
{
    public function test_period_runs_thursday_through_the_wednesday_of_the_week_after_next(): void
    {
        $period = new PayPeriod(CarbonImmutable::parse('2026-11-05'));

        $this->assertSame('2026-11-05', $period->start->toDateString());
        $this->assertTrue($period->start->isThursday());
        $this->assertSame('2026-11-18', $period->end->toDateString());
        $this->assertTrue($period->end->isWednesday());
        $this->assertCount(14, $period->dates());
    }

    public function test_payday_is_the_thursday_after_the_period_ends(): void
    {
        $period = new PayPeriod(CarbonImmutable::parse('2026-11-05'));

        $this->assertSame('2026-11-19', $period->payday()->toDateString());
        $this->assertTrue($period->payday()->isThursday());
    }

    public function test_period_can_start_on_any_day_of_the_week(): void
    {
        $period = new PayPeriod(CarbonImmutable::parse('2026-11-09 15:30'));

        $this->assertSame('2026-11-09 00:00:00', $period->start->toDateTimeString());
        $this->assertSame('2026-11-22', $period->end->toDateString());
        $this->assertSame('2026-11-23', $period->payday()->toDateString());
    }

    public function test_finds_periods_from_a_non_thursday_anchor(): void
    {
        $anchor = CarbonImmutable::parse('2026-01-05');

        $this->assertSame('2026-11-09', PayPeriod::containing(CarbonImmutable::parse('2026-11-15'), $anchor)->start->toDateString());
        $this->assertSame('2025-12-22', PayPeriod::containing(CarbonImmutable::parse('2026-01-04'), $anchor)->start->toDateString());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function datesInPeriods(): array
    {
        return [
            'anchor itself' => ['2026-01-01', '2026-01-01'],
            'last day of first period' => ['2026-01-14', '2026-01-01'],
            'first day of second period' => ['2026-01-15', '2026-01-15'],
            'many periods later, mid-period' => ['2026-11-12', '2026-11-05'],
            'many periods later, final Wednesday' => ['2026-11-18', '2026-11-05'],
            'before the anchor' => ['2025-12-31', '2025-12-18'],
            'exactly one period before the anchor' => ['2025-12-18', '2025-12-18'],
        ];
    }

    #[DataProvider('datesInPeriods')]
    public function test_finds_the_period_containing_a_date(string $date, string $expectedStart): void
    {
        $period = PayPeriod::containing(CarbonImmutable::parse($date), CarbonImmutable::parse('2026-01-01'));

        $this->assertSame($expectedStart, $period->start->toDateString());
        $this->assertTrue($period->contains(CarbonImmutable::parse($date)));
    }

    public function test_previous_and_next_periods_are_adjacent(): void
    {
        $period = new PayPeriod(CarbonImmutable::parse('2026-11-05'));

        $this->assertSame('2026-10-22', $period->previous()->start->toDateString());
        $this->assertSame('2026-11-04', $period->previous()->end->toDateString());
        $this->assertSame('2026-11-19', $period->next()->start->toDateString());
    }
}
