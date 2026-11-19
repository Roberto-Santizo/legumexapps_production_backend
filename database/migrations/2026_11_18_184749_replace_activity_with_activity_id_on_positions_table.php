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
        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('code')->constrained();
            $table->dropColumn('activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->string('activity')->nullable()->after('code');
        });

        DB::table('positions')->update([
            'activity' => DB::raw('(select activities.name from activities where activities.id = positions.activity_id)'),
        ]);

        Schema::table('positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_id');
        });
    }
};
