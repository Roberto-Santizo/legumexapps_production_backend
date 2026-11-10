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
        Schema::create('weekly_plan_task_product_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('raw_material', 150)->nullable();
            $table->float('raw_lbs')->nullable();
            $table->float('classified_lbs')->nullable();
            $table->float('trimmed_lbs')->nullable();
            $table->float('rejected_lbs')->nullable();
            $table->float('packed_lbs')->nullable();
            $table->float('recovery_pct')->nullable();
            $table->float('rejection_pct')->nullable();
            $table->string('observations', 500)->nullable();
            $table->json('extra_values')->nullable();
            $table->timestamps();

            $table->index('weekly_plan_task_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_task_product_records');
    }
};
