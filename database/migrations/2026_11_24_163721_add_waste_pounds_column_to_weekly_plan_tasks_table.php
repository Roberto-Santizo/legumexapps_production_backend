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
            $table->float('waste_pounds')->nullable()->after('weighed_pounds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weekly_plan_tasks', function (Blueprint $table) {
            $table->dropColumn('waste_pounds');
        });
    }
};
