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
        Schema::create('weekly_plan_task_performance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->integer('pallet_number')->nullable();
            $table->integer('boxes')->nullable();
            $table->float('weighed_pounds');
            $table->float('theoretical_pounds')->default(0);
            $table->float('difference_pounds')->default(0);
            $table->timestamps();

            $table->unique(['weekly_plan_task_id', 'pallet_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_task_performance_records');
    }
};
