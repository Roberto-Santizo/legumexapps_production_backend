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
        Schema::create('capture_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->string('label', 100);
            $table->string('data_type', 20);
            $table->string('capture_type', 20)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_calculated')->default(false);
            $table->json('depends_on')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique(['capture_type', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capture_fields');
    }
};
