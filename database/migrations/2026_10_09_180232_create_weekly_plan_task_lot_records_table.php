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
        Schema::create('weekly_plan_task_lot_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('entry_date')->nullable();
            $table->string('lot', 50)->nullable();
            $table->time('recorded_at')->nullable();
            $table->float('intake_lbs')->nullable();
            $table->float('applied_raw_lbs')->nullable();
            $table->float('trimmed_lbs')->nullable();
            $table->float('overripe_lbs')->nullable();
            $table->float('recovery_pct')->nullable();
            $table->float('overripe_pct')->nullable();
            $table->float('grn_balance')->nullable();
            $table->string('observations', 500)->nullable();
            $table->json('extra_values')->nullable();
            $table->timestamps();

            $table->unique(['weekly_plan_task_id', 'lot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_task_lot_records');
    }
};
