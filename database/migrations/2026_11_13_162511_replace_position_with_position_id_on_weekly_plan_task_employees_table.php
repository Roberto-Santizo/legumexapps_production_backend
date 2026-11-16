<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('name')->constrained();
        });

        DB::table('weekly_plan_task_employees')->update([
            'position_id' => DB::raw('(select positions.id from positions where positions.code = weekly_plan_task_employees.position)'),
        ]);

        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->string('position')->nullable()->after('name');
        });

        DB::table('weekly_plan_task_employees')->update([
            'position' => DB::raw('(select positions.code from positions where positions.id = weekly_plan_task_employees.position_id)'),
        ]);

        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
