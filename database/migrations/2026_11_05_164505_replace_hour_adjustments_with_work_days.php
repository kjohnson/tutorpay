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
        Schema::create('work_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->timestamps();
        });

        Schema::create('work_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_block_id')->nullable()->constrained()->nullOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('hour_adjustments');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('hour_adjustments', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->decimal('hours', 5, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('work_blocks');
        Schema::dropIfExists('work_days');
    }
};
