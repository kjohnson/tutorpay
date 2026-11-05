<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WorkDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A day whose blocks have been customized. Days without a WorkDay record
 * simply follow the weekly schedule.
 *
 * @property Carbon $date
 */
#[Fillable(['date'])]
class WorkDay extends Model
{
    /** @use HasFactory<WorkDayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return HasMany<WorkBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(WorkBlock::class)->orderBy('start_time');
    }

    /**
     * Find the customized day for a date, creating it from the weekly
     * schedule the first time the day is changed.
     */
    public static function forDate(CarbonImmutable $date): self
    {
        return DB::transaction(function () use ($date): self {
            $workDay = static::query()->whereDate('date', $date->toDateString())->first();

            if ($workDay !== null) {
                return $workDay;
            }

            $workDay = static::create(['date' => $date->toDateString()]);

            ScheduleBlock::query()
                ->where('day_of_week', $date->dayOfWeek)
                ->get()
                ->each(fn (ScheduleBlock $scheduleBlock) => $workDay->blocks()->create([
                    'schedule_block_id' => $scheduleBlock->id,
                    'start_time' => $scheduleBlock->start_time,
                    'end_time' => $scheduleBlock->end_time,
                    'label' => $scheduleBlock->label,
                ]));

            return $workDay;
        });
    }
}
