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
        Schema::create('assistant_audio_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('conversation_id', 36);
            $table->foreign('conversation_id')->references('id')->on('agent_conversations')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('characters');
            $table->string('provider', 50);
            $table->string('model', 100)->nullable();
            $table->string('voice', 50);
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_audio_summaries');
    }
};
