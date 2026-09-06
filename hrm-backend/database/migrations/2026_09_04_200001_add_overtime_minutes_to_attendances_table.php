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
        if (Schema::hasTable('attendances') && !Schema::hasColumn('attendances', 'overtime_minutes')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->integer('overtime_minutes')->default(0)->after('working_minutes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'overtime_minutes')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('overtime_minutes');
            });
        }
    }
};
