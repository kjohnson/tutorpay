<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->date('payday')->unique();
            $table->decimal('total_hours', 7, 2);
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('work_days', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('date')->constrained()->restrictOnDelete();
        });

        foreach (DB::table('closed_pay_periods')->orderBy('start_date')->get() as $closedPeriod) {
            $paymentId = DB::table('payments')->insertGetId([
                'payday' => CarbonImmutable::parse($closedPeriod->end_date)->addDay()->toDateString(),
                'total_hours' => $closedPeriod->total_hours,
                'hourly_rate' => $closedPeriod->hourly_rate,
                'created_at' => $closedPeriod->closed_at,
                'updated_at' => $closedPeriod->closed_at,
            ]);

            DB::table('work_days')
                ->whereDate('date', '>=', CarbonImmutable::parse($closedPeriod->start_date)->toDateString())
                ->whereDate('date', '<=', CarbonImmutable::parse($closedPeriod->end_date)->toDateString())
                ->update(['payment_id' => $paymentId]);
        }

        Schema::dropIfExists('closed_pay_periods');

        $this->convertSettings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('closed_pay_periods', function (Blueprint $table) {
            $table->id();
            $table->date('start_date')->unique();
            $table->date('end_date')->index();
            $table->decimal('total_hours', 6, 2);
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();
        });

        foreach (DB::table('payments')->orderBy('payday')->get() as $payment) {
            $end = CarbonImmutable::parse($payment->payday)->subDay();

            DB::table('closed_pay_periods')->insert([
                'start_date' => $end->subDays(13)->toDateString(),
                'end_date' => $end->toDateString(),
                'total_hours' => $payment->total_hours,
                'hourly_rate' => $payment->hourly_rate,
                'closed_at' => $payment->created_at,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]);
        }

        Schema::table('work_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::dropIfExists('payments');

        DB::table('settings')->where('key', 'track_hours_from')->delete();
    }

    /**
     * Drop the pay period start date and start tracking hours from the
     * earliest day already recorded.
     */
    private function convertSettings(): void
    {
        DB::table('settings')->where('key', 'pay_period_anchor')->delete();

        $earliestDay = DB::table('work_days')->min('date');

        DB::table('settings')->updateOrInsert(
            ['key' => 'track_hours_from'],
            [
                'value' => CarbonImmutable::parse($earliestDay ?? 'today')->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
