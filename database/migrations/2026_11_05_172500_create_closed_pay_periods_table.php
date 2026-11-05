<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('closed_pay_periods');
    }
};
