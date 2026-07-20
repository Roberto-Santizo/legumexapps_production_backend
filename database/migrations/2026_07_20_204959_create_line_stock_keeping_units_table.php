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
        Schema::create('line_stock_keeping_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_id')->constrained()->on('stock_keeping_units');
            $table->foreignId('line_id')->constrained();
            $table->float('lbs_performance');
            $table->float('accepted_percentage');
            $table->timestamps();
            $table->boolean('payment_method');
            $table->boolean('status')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_stock_keeping_units');
    }
};
