<?php

namespace App\Models;

use App\Models\Concerns\HasTimeRange;
use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A recurring block of tutoring time on the weekly schedule.
 *
 * @property int $day_of_week 0 (Sunday) through 6 (Saturday)
 * @property string|null $label
 */
#[Fillable(['day_of_week', 'start_time', 'end_time', 'label'])]
class ScheduleBlock extends Model
{
    /** @use HasFactory<ScheduleBlockFactory> */
    use HasFactory, HasTimeRange;

    /**
     * Day names keyed by Carbon's day-of-week number, in the Monday through
     * Sunday order the weekly schedule is managed in.
     *
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        0 => 'Sunday',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
        ];
    }
}
