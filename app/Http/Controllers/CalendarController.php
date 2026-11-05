<?php

namespace App\Http\Controllers;

use App\Support\Timesheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Show the Monday–Sunday week containing the requested date (or today).
     */
    public function show(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = CarbonImmutable::today();
        $date = isset($validated['date']) ? CarbonImmutable::parse($validated['date']) : $today;
        $weekStart = $date->startOfWeek(CarbonImmutable::MONDAY);

        return view('calendar', [
            'week' => Timesheet::forRange($weekStart, $weekStart->addDays(6)),
            'weekStart' => $weekStart,
            'today' => $today,
            'isCurrentWeek' => $weekStart->isSameDay($today->startOfWeek(CarbonImmutable::MONDAY)),
        ]);
    }
}
