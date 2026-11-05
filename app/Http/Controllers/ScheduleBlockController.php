<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleBlockRequest;
use App\Models\ScheduleBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScheduleBlockController extends Controller
{
    /**
     * Show the weekly schedule.
     */
    public function index(): View
    {
        $blocksByDay = ScheduleBlock::query()
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('schedule', [
            'blocksByDay' => $blocksByDay,
            'weeklyHours' => round($blocksByDay->flatten()->sum('hours'), 2),
        ]);
    }

    /**
     * Add a block of time to the weekly schedule.
     */
    public function store(StoreScheduleBlockRequest $request): RedirectResponse
    {
        ScheduleBlock::create([
            'day_of_week' => $request->integer('day_of_week'),
            ...$request->blockAttributes(),
        ]);

        return redirect()->route('schedule.index')->with('status', 'Time added to the weekly schedule.');
    }

    /**
     * Remove a block of time from the weekly schedule.
     */
    public function destroy(ScheduleBlock $scheduleBlock): RedirectResponse
    {
        $scheduleBlock->delete();

        return redirect()->route('schedule.index')->with('status', 'Time removed from the weekly schedule.');
    }
}
