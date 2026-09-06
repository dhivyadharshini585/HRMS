<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->decimal('default_annual_allocation', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_unpaid')->default(false);
            $table->timestamps();
        });

        // Seed the exact 8 required leave types
        DB::table('leave_types')->insert([
            [
                'name' => 'Casual Leave',
                'description' => 'Casual leave for personal or incidental matters',
                'default_annual_allocation' => 12.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Sick Leave',
                'description' => 'Leave for medical recovery and health reasons',
                'default_annual_allocation' => 12.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Earned Leave',
                'description' => 'Accumulated annual leave accrued over service period',
                'default_annual_allocation' => 15.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paid Leave',
                'description' => 'General paid leave allowance',
                'default_annual_allocation' => 10.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Unpaid Leave',
                'description' => 'Leave without pay',
                'default_annual_allocation' => 0.00,
                'is_active' => true,
                'is_unpaid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Maternity Leave',
                'description' => 'Maternity leave allowance for female employees',
                'default_annual_allocation' => 180.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paternity Leave',
                'description' => 'Paternity leave allowance for male employees',
                'default_annual_allocation' => 15.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Compensatory Off',
                'description' => 'Compensatory off balance earned for overtime/weekend work',
                'default_annual_allocation' => 0.00,
                'is_active' => true,
                'is_unpaid' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
