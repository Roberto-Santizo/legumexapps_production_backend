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
        Schema::create('draft_weekly_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->integer('boxes');
            $table->string('destination');
            $table->float('hours');
            $table->timestamp('operation_date')->nullable();
            $table->foreignId('draft_weekly_plan_id')->constrained();
            $table->foreignId('sku_id')->constrained()->on('stock_keeping_units');
            $table->foreignId('line_id')->nullable()->constrained()->on('lines');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('draft_weekly_plan_tasks');
    }
};
