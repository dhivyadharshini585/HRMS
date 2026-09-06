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
        if (Schema::hasTable('shifts') && !Schema::hasColumn('shifts', 'grace_period_minutes')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->integer('grace_period_minutes')->default(15)->after('end_time');
                $table->boolean('overtime_enabled')->default(false)->after('grace_period_minutes');
                $table->integer('overtime_threshold_minutes')->default(60)->after('overtime_enabled');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('shifts') && Schema::hasColumn('shifts', 'grace_period_minutes')) {
            Schema::table('shifts', function (Blueprint $table) {
                $table->dropColumn(['grace_period_minutes', 'overtime_enabled', 'overtime_threshold_minutes']);
            });
        }
    }
};
