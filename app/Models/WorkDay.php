<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\WorkDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A day with its own copy of its blocks: either customized, frozen by a
 * schedule change, or paid. Days without a WorkDay follow the weekly schedule.
 *
 * @property Carbon $date
 * @property int|null $payment_id
 */
#[Fillable(['date', 'payment_id'])]
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
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Whether the given date has been included in a payment.
     */
    public static function isPaid(CarbonInterface $date): bool
    {
        return static::query()
            ->whereDate('date', $date->toDateString())
            ->whereNotNull('payment_id')
            ->exists();
    }

    /**
     * Copy the current weekly schedule onto every untouched past day of the
     * given weekday, so a schedule change only affects today onward.
     */
    public static function freezeUnpaidPast(int $dayOfWeek): void
    {
        $start = Setting::trackHoursFrom();
        $yesterday = CarbonImmutable::yesterday();

        if ($start->isAfter($yesterday)) {
            return;
        }

        $existing = static::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $yesterday->toDateString())
            ->pluck('date')
            ->map(fn (Carbon $date): string => $date->toDateString())
            ->flip();

        $date = $start->dayOfWeek === $dayOfWeek ? $start : $start->next($dayOfWeek);

        for (; ! $date->isAfter($yesterday); $date = $date->addWeek()) {
            if (! $existing->has($date->toDateString())) {
                static::forDate($date);
            }
        }
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
