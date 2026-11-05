<?php

namespace App\Support;

use App\Models\ScheduleBlock;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out the blocks and hours for each day of a pay period. A day uses
 * its own blocks once it has been customized, and the weekly schedule
 * otherwise.
 */
final class Timesheet
{
    /**
     * @param  list<array{date: CarbonImmutable, blocks: Collection<int, ScheduleBlock|WorkBlock>, isCustomized: bool, totalHours: float}>  $days
     */
    private function __construct(public readonly PayPeriod $period, public readonly array $days) {}

    public static function for(PayPeriod $period): self
    {
        $scheduleByDay = ScheduleBlock::query()
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        $workDaysByDate = WorkDay::query()
            ->with('blocks')
            ->whereDate('date', '>=', $period->start->toDateString())
            ->whereDate('date', '<=', $period->end->toDateString())
            ->get()
            ->keyBy(fn (WorkDay $workDay): string => $workDay->date->toDateString());

        $days = array_map(function (CarbonImmutable $date) use ($scheduleByDay, $workDaysByDate): array {
            $workDay = $workDaysByDate->get($date->toDateString());
            $blocks = $workDay?->blocks ?? $scheduleByDay->get($date->dayOfWeek, collect());

            return [
                'date' => $date,
                'blocks' => $blocks,
                'isCustomized' => $workDay !== null,
                'totalHours' => round($blocks->sum('hours'), 2),
            ];
        }, $period->dates());

        return new self($period, $days);
    }

    /**
     * Format an hour count without trailing zeros, e.g. 1.5 or 2.
     */
    public static function formatHours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2), '0'), '.');
    }

    public function totalHours(): float
    {
        return round(array_sum(array_column($this->days, 'totalHours')), 2);
    }

    /**
     * Total hours for the first or second week of the period (1 or 2).
     */
    public function weekHours(int $week): float
    {
        $days = array_slice($this->days, ($week - 1) * 7, 7);

        return round(array_sum(array_column($days, 'totalHours')), 2);
    }
}
