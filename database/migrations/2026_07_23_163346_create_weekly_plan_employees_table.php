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
        Schema::create('weekly_plan_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('weekly_plan_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_employees');
    }
};
