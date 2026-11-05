<?php

namespace App\Http\Controllers;

use App\Models\ClosedPayPeriod;
use App\Models\Setting;
use App\Models\WorkDay;
use App\Support\PayPeriod;
use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ClosedPayPeriodController extends Controller
{
    /**
     * Mark the pay period starting on the given date as paid and lock it.
     */
    public function store(string $date): RedirectResponse
    {
        $start = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        abort_unless($start !== null && $start->toDateString() === $date, 404);

        $period = PayPeriod::containing($start, Setting::payPeriodAnchor());
        abort_unless($period->start->equalTo($start), 404);

        $backToPeriod = redirect()->route('pay-period.show', ['date' => $period->start->toDateString()]);

        if ($period->start->isAfter(CarbonImmutable::today())) {
            return $backToPeriod->with('error', 'A pay period can’t be marked as paid before it starts.');
        }

        if (ClosedPayPeriod::forPeriod($period) !== null) {
            return $backToPeriod->with('error', 'This pay period has already been marked as paid.');
        }

        DB::transaction(function () use ($period): void {
            foreach ($period->dates() as $day) {
                WorkDay::forDate($day);
            }

            ClosedPayPeriod::create([
                'start_date' => $period->start->toDateString(),
                'end_date' => $period->end->toDateString(),
                'total_hours' => Timesheet::for($period)->totalHours(),
                'hourly_rate' => Setting::hourlyRate(),
                'closed_at' => now(),
            ]);
        });

        return $backToPeriod;
    }
}
