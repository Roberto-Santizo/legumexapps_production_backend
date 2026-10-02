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
        Schema::create('weekly_plan_task_timeouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('timeout_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->float('duration_hours')->nullable();
            $table->string('observation', 500)->nullable();
            $table->timestamps();

            $table->index(['weekly_plan_task_id', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_task_timeouts');
    }
};
