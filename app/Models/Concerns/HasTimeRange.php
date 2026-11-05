<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Shared behaviour for models with a start_time and end_time (HH:MM:SS).
 *
 * @property string $start_time
 * @property string $end_time
 * @property-read float $hours
 * @property-read string $time_range
 */
trait HasTimeRange
{
    /**
     * The length of the block in hours.
     */
    protected function hours(): Attribute
    {
        return Attribute::get(function (): float {
            $start = CarbonImmutable::createFromTimeString($this->start_time);
            $end = CarbonImmutable::createFromTimeString($this->end_time);

            return round($start->diffInMinutes($end) / 60, 2);
        });
    }

    /**
     * The block's times for display, e.g. "3:00 PM – 4:30 PM".
     */
    protected function timeRange(): Attribute
    {
        return Attribute::get(fn (): string => self::formatTime($this->start_time).' – '.self::formatTime($this->end_time));
    }

    /**
     * Format a stored time (HH:MM:SS) for display, e.g. "3:30 PM".
     */
    public static function formatTime(string $time): string
    {
        return CarbonImmutable::createFromTimeString($time)->format('g:i A');
    }

    /**
     * Format a stored time (HH:MM:SS) for a time input, e.g. "15:30".
     */
    public static function inputTime(string $time): string
    {
        return substr($time, 0, 5);
    }
}
