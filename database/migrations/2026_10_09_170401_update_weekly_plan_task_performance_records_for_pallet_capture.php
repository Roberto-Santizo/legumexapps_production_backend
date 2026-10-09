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
        Schema::table('weekly_plan_task_performance_records', function (Blueprint $table) {
            $table->renameColumn('weighed_pounds', 'net_weight');
            $table->renameColumn('theoretical_pounds', 'ticket_weight');
            $table->renameColumn('difference_pounds', 'difference');
        });

        Schema::table('weekly_plan_task_performance_records', function (Blueprint $table) {
            $table->float('net_weight')->nullable()->change();
            $table->float('ticket_weight')->nullable()->default(null)->change();
            $table->float('difference')->nullable()->default(null)->change();

            $table->string('lot', 50)->nullable()->after('pallet_number');
            $table->time('recorded_at')->nullable()->after('lot');
            $table->float('liters')->nullable()->after('boxes');
            $table->string('status', 20)->nullable()->after('liters');
            $table->float('scale_weight')->nullable()->after('status');
            $table->float('tare')->nullable()->after('scale_weight');
            $table->string('observations', 500)->nullable();
            $table->json('extra_values')->nullable();
        });

        DB::table('weekly_plan_task_performance_records')
            ->whereNull('boxes')
            ->update(['ticket_weight' => null, 'difference' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('weekly_plan_task_performance_records')->whereNull('net_weight')->update(['net_weight' => 0]);
        DB::table('weekly_plan_task_performance_records')->whereNull('ticket_weight')->update(['ticket_weight' => 0]);
        DB::table('weekly_plan_task_performance_records')->whereNull('difference')->update(['difference' => 0]);

        Schema::table('weekly_plan_task_performance_records', function (Blueprint $table) {
            $table->dropColumn(['lot', 'recorded_at', 'liters', 'status', 'scale_weight', 'tare', 'observations', 'extra_values']);

            $table->float('net_weight')->nullable(false)->change();
            $table->float('ticket_weight')->nullable(false)->default(0)->change();
            $table->float('difference')->nullable(false)->default(0)->change();
        });

        Schema::table('weekly_plan_task_performance_records', function (Blueprint $table) {
            $table->renameColumn('net_weight', 'weighed_pounds');
            $table->renameColumn('ticket_weight', 'theoretical_pounds');
            $table->renameColumn('difference', 'difference_pounds');
        });
    }
};
