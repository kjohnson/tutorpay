<?php

namespace App\Support;

use App\Models\ScheduleBlock;
use App\Models\Setting;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Works out the blocks and hours for each day in a date range. A day uses
 * its own blocks once it has a WorkDay (customized, frozen or paid), and the
 * weekly schedule otherwise. The schedule doesn't apply to days before the
 * "track hours from" date.
 */
final class Timesheet
{
    /**
     * @param  list<array{date: CarbonImmutable, blocks: Collection<int, ScheduleBlock|WorkBlock>, isCustomized: bool, isTracked: bool, differsFromSchedule: bool, paymentId: int|null, totalHours: float}>  $days
     */
    private function __construct(public readonly array $days) {}

    public static function forRange(CarbonImmutable $start, CarbonImmutable $end): self
    {
        if ($start->isAfter($end)) {
            return new self([]);
        }

        $scheduleByDay = ScheduleBlock::query()
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        $workDaysByDate = WorkDay::query()
            ->with('blocks')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (WorkDay $workDay): string => $workDay->date->toDateString());

        $trackHoursFrom = Setting::trackHoursFrom();
        $days = [];

        foreach (CarbonPeriod::create($start->startOfDay(), $end->startOfDay()) as $date) {
            $date = CarbonImmutable::instance($date);
            $workDay = $workDaysByDate->get($date->toDateString());
            $isTracked = ! $date->isBefore($trackHoursFrom);
            $scheduled = $isTracked ? $scheduleByDay->get($date->dayOfWeek, collect()) : collect();
            $blocks = $workDay?->blocks ?? $scheduled;

            $days[] = [
                'date' => $date,
                'blocks' => $blocks,
                'isCustomized' => $workDay !== null,
                'isTracked' => $isTracked,
                'differsFromSchedule' => $isTracked && $workDay !== null && self::signature($blocks) !== self::signature($scheduled),
                'paymentId' => $workDay?->payment_id,
                'totalHours' => round($blocks->sum('hours'), 2),
            ];
        }

        return new self($days);
    }

    /**
     * Unpaid days with hours from the tracking start date through the given
     * date (inclusive). Through today this is the running unpaid total;
     * through a payday it is the days that payment can include.
     */
    public static function unpaidThrough(CarbonImmutable $end): self
    {
        $timesheet = self::forRange(Setting::trackHoursFrom(), $end);

        return new self(array_values(array_filter(
            $timesheet->days,
            fn (array $day): bool => $day['paymentId'] === null && $day['totalHours'] > 0,
        )));
    }

    /**
     * A comparable summary of a day's blocks: times and labels, in order.
     *
     * @param  Collection<int, ScheduleBlock|WorkBlock>  $blocks
     * @return list<string>
     */
    private static function signature(Collection $blocks): array
    {
        return $blocks
            ->map(fn (ScheduleBlock|WorkBlock $block): string => implode('|', [
                $block::inputTime($block->start_time),
                $block::inputTime($block->end_time),
                (string) $block->label,
            ]))
            ->sort()
            ->values()
            ->all();
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
     * @return list<string>
     */
    public function dateStrings(): array
    {
        return array_map(fn (array $day): string => $day['date']->toDateString(), $this->days);
    }

    /**
     * The days grouped into Monday–Sunday weeks, keyed by each week's Monday.
     *
     * @return array<string, list<array{date: CarbonImmutable, blocks: Collection<int, ScheduleBlock|WorkBlock>, isCustomized: bool, isTracked: bool, differsFromSchedule: bool, paymentId: int|null, totalHours: float}>>
     */
    public function byWeek(): array
    {
        $weeks = [];

        foreach ($this->days as $day) {
            $weeks[$day['date']->startOfWeek(CarbonImmutable::MONDAY)->toDateString()][] = $day;
        }

        return $weeks;
    }
}
