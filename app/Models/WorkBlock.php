<?php

namespace App\Models;

use App\Models\Concerns\HasTimeRange;
use Database\Factories\WorkBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A block of tutoring time on a specific day.
 *
 * @property int $work_day_id
 * @property int|null $schedule_block_id The weekly schedule block this was copied from, if any.
 * @property string|null $label
 */
#[Fillable(['schedule_block_id', 'start_time', 'end_time', 'label'])]
class WorkBlock extends Model
{
    /** @use HasFactory<WorkBlockFactory> */
    use HasFactory, HasTimeRange;

    /**
     * @return BelongsTo<WorkDay, $this>
     */
    public function workDay(): BelongsTo
    {
        return $this->belongsTo(WorkDay::class);
    }
}
