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
        Schema::create('line_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_field_id')->constrained();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->string('label', 100)->nullable();
            $table->timestamps();

            $table->unique(['line_id', 'capture_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_fields');
    }
};
