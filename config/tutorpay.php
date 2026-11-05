<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Track Hours From
    |--------------------------------------------------------------------------
    |
    | The earliest day whose hours count as unpaid. The weekly schedule
    | repeats forever, so this keeps older days out of the unpaid total.
    | Defaults to today when not set here or on the Settings page.
    |
    */

    'track_hours_from' => env('TUTORPAY_TRACK_HOURS_FROM'),

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
