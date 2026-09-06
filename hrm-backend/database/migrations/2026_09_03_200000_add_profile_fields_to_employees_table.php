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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('profile_photo_path')->nullable()->after('email');
            $table->string('emergency_contact_name')->nullable()->after('postal_code');
            $table->string('emergency_contact_relationship')->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_relationship');
            $table->string('job_level', 50)->nullable()->after('designation_id');
            $table->foreignId('manager_id')->nullable()->after('job_level')->constrained('employees')->nullOnDelete();
            $table->string('work_location', 100)->nullable()->after('employment_type');
            $table->string('work_shift', 50)->nullable()->after('work_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
            $table->dropColumn([
                'profile_photo_path',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'job_level',
                'manager_id',
                'work_location',
                'work_shift',
            ]);
        });
    }
};
