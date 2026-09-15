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
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                if (!Schema::hasColumn('attendances', 'check_in_latitude')) {
                    $table->decimal('check_in_latitude', 10, 8)->nullable()->after('check_in');
                }
                if (!Schema::hasColumn('attendances', 'check_in_longitude')) {
                    $table->decimal('check_in_longitude', 11, 8)->nullable()->after('check_in_latitude');
                }
                if (!Schema::hasColumn('attendances', 'check_in_device')) {
                    $table->string('check_in_device')->nullable()->after('check_in_longitude');
                }

                if (!Schema::hasColumn('attendances', 'check_out_latitude')) {
                    $table->decimal('check_out_latitude', 10, 8)->nullable()->after('check_out');
                }
                if (!Schema::hasColumn('attendances', 'check_out_longitude')) {
                    $table->decimal('check_out_longitude', 11, 8)->nullable()->after('check_out_latitude');
                }
                if (!Schema::hasColumn('attendances', 'check_out_device')) {
                    $table->string('check_out_device')->nullable()->after('check_out_longitude');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', function (Blueprint $table) {
                $columnsToDrop = [];
                foreach (['check_in_latitude', 'check_in_longitude', 'check_in_device', 'check_out_latitude', 'check_out_longitude', 'check_out_device'] as $col) {
                    if (Schema::hasColumn('attendances', $col)) {
                        $columnsToDrop[] = $col;
                    }
                }

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
