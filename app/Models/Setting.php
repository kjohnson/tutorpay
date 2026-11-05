<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Simple key/value application settings.
 *
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Read a setting, falling back to the given default when it is not stored.
     */
    public static function valueFor(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Store a setting value.
     */
    public static function store(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * A date that begins a pay period, anchoring the bi-weekly cycle.
     */
    public static function payPeriodAnchor(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            static::valueFor('pay_period_anchor', config('tutorpay.pay_period_anchor'))
        )->startOfDay();
    }

    /**
     * The start date can't change once any pay period has been marked as paid,
     * since that would shift the paid periods.
     */
    public static function payPeriodAnchorIsLocked(): bool
    {
        return ClosedPayPeriod::query()->exists();
    }

    /**
     * The hourly rate used to estimate pay, if one is configured.
     */
    public static function hourlyRate(): ?float
    {
        $rate = static::valueFor('hourly_rate', config('tutorpay.hourly_rate'));

        return filled($rate) ? (float) $rate : null;
    }
}
