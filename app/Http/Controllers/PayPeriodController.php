<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\PayPeriod;
use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayPeriodController extends Controller
{
    /**
     * Show the timesheet for the pay period containing the requested date
     * (or today when no date is given).
     */
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = CarbonImmutable::today();
        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : $today;

        $period = PayPeriod::containing($date, Setting::payPeriodAnchor());

        return view('pay-period', [
            'timesheet' => Timesheet::for($period),
            'today' => $today,
            'isCurrentPeriod' => $period->contains($today),
            'hourlyRate' => Setting::hourlyRate(),
        ]);
    }
}
