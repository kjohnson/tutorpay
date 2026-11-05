<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Setting;
use App\Models\WorkDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConvertClosedPayPeriodsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_closed_pay_periods_become_payments(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        DB::table('settings')->insert(['key' => 'pay_period_anchor', 'value' => '2026-11-05', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('closed_pay_periods')->insert([
            'start_date' => '2026-10-22',
            'end_date' => '2026-11-04',
            'total_hours' => 9,
            'hourly_rate' => 21,
            'closed_at' => '2026-11-05 17:28:44',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach (['2026-10-21', '2026-10-22', '2026-11-04', '2026-11-05'] as $date) {
            DB::table('work_days')->insert(['date' => $date, 'created_at' => now(), 'updated_at' => now()]);
        }

        Artisan::call('migrate');

        $payment = Payment::sole();
        $this->assertSame('2026-11-05', $payment->payday->toDateString());
        $this->assertSame('9.00', $payment->total_hours);
        $this->assertSame('21.00', $payment->hourly_rate);
        $this->assertSame(
            ['2026-10-22', '2026-11-04'],
            $payment->days->map(fn (WorkDay $day): string => $day->date->toDateString())->all(),
        );
        $this->assertSame('2026-10-21', Setting::trackHoursFrom()->toDateString());
        $this->assertNull(Setting::valueFor('pay_period_anchor'));
    }
}
