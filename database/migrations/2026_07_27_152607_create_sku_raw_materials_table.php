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
        Schema::create('sku_raw_materials', function (Blueprint $table) {
            $table->id();
            $table->float('percentage');
            $table->foreignId('stock_keeping_unit_id')->constrained();
            $table->foreignId('raw_material_id')->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sku_raw_materials');
    }
};
