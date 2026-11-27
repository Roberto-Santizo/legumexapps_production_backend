<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operation tables emptied before moving the configuration to line SKUs.
     *
     * @var list<string>
     */
    private array $operationTables = [
        'weekly_plan_task_logs',
        'weekly_plan_task_observations',
        'weekly_plan_task_employees',
        'weekly_plan_task_performance_records',
        'weekly_plan_task_lot_records',
        'weekly_plan_task_product_records',
        'weekly_plan_task_timeouts',
        'packing_material_transaction_items',
        'packing_material_transactions',
        'weekly_plan_tasks',
        'weekly_plans',
        'draft_weekly_plan_tasks',
        'draft_weekly_plans',
        'assistant_audio_summaries',
        'agent_conversation_messages',
        'agent_conversations',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->operationTables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();

        Schema::create('line_sku_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_sku_id')->constrained('line_stock_keeping_units')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['line_sku_id', 'activity_id']);
        });

        DB::table('line_sku_activities')->insertUsing(
            ['line_sku_id', 'activity_id', 'quantity', 'created_at', 'updated_at'],
            DB::table('line_activities')
                ->join('line_stock_keeping_units', 'line_stock_keeping_units.line_id', '=', 'line_activities.line_id')
                ->select([
                    'line_stock_keeping_units.id',
                    'line_activities.activity_id',
                    'line_activities.quantity',
                    DB::raw('now()'),
                    DB::raw('now()'),
                ])
        );

        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_activity_id');
            $table->foreignId('line_sku_activity_id')->nullable()->after('position_id')->constrained('line_sku_activities');
        });

        Schema::drop('line_activities');

        DB::table('positions')->delete();

        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropConstrainedForeignId('line_id');
            $table->foreignId('line_sku_id')->after('code')->constrained('line_stock_keeping_units')->cascadeOnDelete();
            $table->unique(['line_sku_id', 'code']);
        });
    }

    /**
     * Reverse the migrations. Only the schema is restored; truncated data is not.
     */
    public function down(): void
    {
        Schema::create('line_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['line_id', 'activity_id']);
        });

        Schema::table('weekly_plan_task_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('line_sku_activity_id');
            $table->foreignId('line_activity_id')->nullable()->after('position_id')->constrained();
        });

        DB::table('positions')->delete();

        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['line_sku_id', 'code']);
            $table->dropConstrainedForeignId('line_sku_id');
            $table->foreignId('line_id')->nullable()->constrained();
            $table->unique('code');
        });

        Schema::dropIfExists('line_sku_activities');
    }
};
