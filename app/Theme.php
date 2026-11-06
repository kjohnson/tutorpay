<?php

namespace App;

/**
 * The colour theme chosen for this browser, stored in a cookie. System
 * follows the device setting.
 */
enum Theme: string
{
    public const COOKIE = 'theme';

    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    /**
     * The theme saved in the current request's cookie, or System.
     */
    public static function current(): self
    {
        return self::tryFrom((string) request()->cookie(self::COOKIE)) ?? self::System;
    }

    public function label(): string
    {
        return match ($this) {
            self::System => 'System default',
            self::Light => 'Light',
            self::Dark => 'Dark',
        };
    }
}
