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
        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->foreignId('line_activity_id')->nullable()->after('position_id')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_activity_id');
        });
    }
};
