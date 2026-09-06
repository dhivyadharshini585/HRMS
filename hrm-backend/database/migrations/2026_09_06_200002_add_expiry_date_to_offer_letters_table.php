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
        if (Schema::hasTable('offer_letters') && !Schema::hasColumn('offer_letters', 'expiry_date')) {
            Schema::table('offer_letters', function (Blueprint $table) {
                $table->date('expiry_date')->nullable()->after('joining_date')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('offer_letters') && Schema::hasColumn('offer_letters', 'expiry_date')) {
            Schema::table('offer_letters', function (Blueprint $table) {
                $table->dropColumn('expiry_date');
            });
        }
    }
};
