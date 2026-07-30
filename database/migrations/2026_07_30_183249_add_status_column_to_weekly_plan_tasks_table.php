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
        Schema::table('weekly_plan_tasks', function (Blueprint $table) {
            $table->integer('status')->default(1)->after('line_sku_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_plan_tasks', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
