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
        Schema::create('line_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained();
            $table->foreignId('line_dependent_id')->constrained()->on('lines');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_dependencies');
    }
};
