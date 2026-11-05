<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A payment made on a payday for a chosen set of days. Its days are locked.
 *
 * @property Carbon $payday
 * @property string $total_hours
 * @property string|null $hourly_rate
 * @property string|null $note
 */
#[Fillable(['payday', 'total_hours', 'hourly_rate', 'note'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payday' => 'date',
            'total_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<WorkDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(WorkDay::class)->orderBy('date');
    }

    /**
     * The amount paid, when an hourly rate was set.
     */
    public function totalPay(): ?float
    {
        return $this->hourly_rate === null ? null : round((float) $this->total_hours * (float) $this->hourly_rate, 2);
    }
}
