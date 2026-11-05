<?php

namespace App\Models;

use App\Support\PayPeriod;
use Carbon\CarbonInterface;
use Database\Factories\ClosedPayPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A pay period that has been marked as paid. Its days are locked, and the
 * hours and rate it was paid at are recorded.
 *
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $total_hours
 * @property string|null $hourly_rate
 * @property Carbon $closed_at
 */
#[Fillable(['start_date', 'end_date', 'total_hours', 'hourly_rate', 'closed_at'])]
class ClosedPayPeriod extends Model
{
    /** @use HasFactory<ClosedPayPeriodFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'total_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The closed record for a pay period, if it has been closed.
     */
    public static function forPeriod(PayPeriod $period): ?self
    {
        return static::query()->whereDate('start_date', $period->start->toDateString())->first();
    }

    /**
     * Whether the given date falls inside any closed pay period.
     */
    public static function coversDate(CarbonInterface $date): bool
    {
        return static::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->exists();
    }

    /**
     * The amount paid for the period, when an hourly rate was set.
     */
    public function totalPay(): ?float
    {
        return $this->hourly_rate === null ? null : round((float) $this->total_hours * (float) $this->hourly_rate, 2);
    }
}
