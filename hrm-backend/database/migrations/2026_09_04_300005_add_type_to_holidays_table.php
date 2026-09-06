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
        if (Schema::hasTable('holidays') && !Schema::hasColumn('holidays', 'holiday_type')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->string('holiday_type')->default('Company')->after('holiday_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('holidays') && Schema::hasColumn('holidays', 'holiday_type')) {
            Schema::table('holidays', function (Blueprint $table) {
                $table->dropColumn('holiday_type');
            });
        }
    }
};
