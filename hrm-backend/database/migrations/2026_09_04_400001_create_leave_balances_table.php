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
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->decimal('allocated_days', 5, 2)->default(0);
            $table->decimal('used_days', 5, 2)->default(0);
            $table->decimal('remaining_days', 5, 2)->default(0);
            $table->integer('year')->default(2026);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year'], 'emp_leave_year_unique');
        });

        // Populate initial 2026 balances for existing baseline employees
        $employees = DB::table('employees')->get();
        $leaveTypes = DB::table('leave_types')->get();
        $currentYear = 2026;

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $type) {
                $allocated = (float) $type->default_annual_allocation;
                DB::table('leave_balances')->insertOrIgnore([
                    'employee_id' => $employee->id,
                    'leave_type_id' => $type->id,
                    'allocated_days' => $allocated,
                    'used_days' => 0.00,
                    'remaining_days' => $allocated,
                    'year' => $currentYear,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
