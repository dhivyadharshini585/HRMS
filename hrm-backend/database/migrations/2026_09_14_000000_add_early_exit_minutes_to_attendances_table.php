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
        if (Schema::hasTable('attendances') && !Schema::hasColumn('attendances', 'early_exit_minutes')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->integer('early_exit_minutes')->default(0)->after('overtime_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'early_exit_minutes')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('early_exit_minutes');
            });
        }
    }
};
