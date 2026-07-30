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
        Schema::create('packing_material_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->string('responsable');
            $table->string('observations')->nullable();
            $table->string('responsable_signature');
            $table->string('user_signature');
            $table->integer('type')->default(1);
            $table->foreignId('user_id');
            $table->foreignId('weekly_plan_task_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packing_material_transactions');
    }
};
