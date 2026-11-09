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
        Schema::create('weekly_plan_task_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_plan_task_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('position');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['weekly_plan_task_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_task_employees');
    }
};
