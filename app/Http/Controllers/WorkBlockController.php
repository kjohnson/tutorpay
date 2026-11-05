<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTimeBlockRequest;
use App\Models\ScheduleBlock;
use App\Models\WorkBlock;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

/**
 * Adds, edits and deletes blocks of time on a specific day. The first change
 * to a day copies the weekly schedule onto it, so the day keeps its own
 * blocks from then on.
 */
class WorkBlockController extends Controller
{
    /**
     * Add a new block of hours to a day.
     */
    public function store(SaveTimeBlockRequest $request, string $date): RedirectResponse
    {
        $day = $this->parseDate($date);

        WorkDay::forDate($day)->blocks()->create($request->blockAttributes());

        return $this->backToDay($day, 'Block added.');
    }

    /**
     * Edit a block on a day that has already been customized.
     */
    public function update(SaveTimeBlockRequest $request, WorkBlock $workBlock): RedirectResponse
    {
        $workBlock->update($request->blockAttributes());

        return $this->backToDay(CarbonImmutable::instance($workBlock->workDay->date), 'Block updated.');
    }

    /**
     * Edit a weekly-schedule block for one day only.
     */
    public function updateScheduled(SaveTimeBlockRequest $request, string $date, ScheduleBlock $scheduleBlock): RedirectResponse
    {
        $day = $this->parseDate($date);

        $this->copyOfScheduleBlock($day, $scheduleBlock)->update($request->blockAttributes());

        return $this->backToDay($day, 'Block updated for this day.');
    }

    /**
     * Delete a block from a day that has already been customized.
     */
    public function destroy(WorkBlock $workBlock): RedirectResponse
    {
        $day = CarbonImmutable::instance($workBlock->workDay->date);

        $workBlock->delete();

        return $this->backToDay($day, 'Block deleted.');
    }

    /**
     * Delete a weekly-schedule block from one day only.
     */
    public function destroyScheduled(string $date, ScheduleBlock $scheduleBlock): RedirectResponse
    {
        $day = $this->parseDate($date);

        $this->copyOfScheduleBlock($day, $scheduleBlock)->delete();

        return $this->backToDay($day, 'Block deleted for this day.');
    }

    /**
     * Discard a day's changes so it follows the weekly schedule again.
     */
    public function reset(string $date): RedirectResponse
    {
        $day = $this->parseDate($date);

        WorkDay::query()->whereDate('date', $day->toDateString())->delete();

        return $this->backToDay($day, 'Day reset to the weekly schedule.');
    }

    /**
     * Customize the day if needed and return its copy of the schedule block.
     */
    private function copyOfScheduleBlock(CarbonImmutable $day, ScheduleBlock $scheduleBlock): WorkBlock
    {
        abort_unless($scheduleBlock->day_of_week === $day->dayOfWeek, 404);

        return WorkDay::forDate($day)->blocks()->where('schedule_block_id', $scheduleBlock->id)->firstOrFail();
    }

    private function parseDate(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        abort_unless($parsed !== null && $parsed->toDateString() === $date, 404);

        return $parsed;
    }

    private function backToDay(CarbonImmutable $day, string $status): RedirectResponse
    {
        return redirect()
            ->route('pay-period.show', ['date' => $day->toDateString()])
            ->with('status', $status.' ('.$day->format('l, M j').')');
    }
}
