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
        Schema::create('weekly_plan_tasks', function (Blueprint $table) {
            $table->id();
            $table->integer('boxes');
            $table->integer('produced_boxes')->nullable();
            $table->float('pallets');
            $table->float('produced_pallets')->nullable();
            $table->float('hours');
            $table->float('weighed_pounds')->nullable();
            $table->string('destination');
            $table->timestamp('operation_date')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->foreignId('weekly_plan_id')->constrained();
            $table->foreignId('line_sku_id')->constrained()->on('line_stock_keeping_units');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_plan_tasks');
    }
};
