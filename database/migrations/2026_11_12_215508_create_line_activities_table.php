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
        Schema::create('line_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['line_id', 'activity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_activities');
    }
};
