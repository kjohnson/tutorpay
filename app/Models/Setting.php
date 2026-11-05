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
     * The earliest day whose hours count toward unpaid hours.
     */
    public static function trackHoursFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse(
            static::valueFor('track_hours_from', config('tutorpay.track_hours_from')) ?? 'today'
        )->startOfDay();
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
