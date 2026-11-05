<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pay Period Anchor
    |--------------------------------------------------------------------------
    |
    | Any date that begins a pay period. Every pay period runs fourteen
    | days from its start date, and the tutor is paid on the day after it
    | ends. This can be overridden from the Settings page.
    |
    */

    'pay_period_anchor' => env('TUTORPAY_PAY_PERIOD_ANCHOR', '2026-01-01'),

    /*
    |--------------------------------------------------------------------------
    | Hourly Rate
    |--------------------------------------------------------------------------
    |
    | Optional default hourly rate used to estimate gross pay.
    |
    */

    'hourly_rate' => env('TUTORPAY_HOURLY_RATE'),

];
