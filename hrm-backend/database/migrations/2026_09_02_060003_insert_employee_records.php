<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert employee records for existing test users if they don't exist
        $users = [
            ['email' => 'superadmin@hrms.local', 'code' => 'EMP-001'],
            ['email' => 'hradmin@hrms.local', 'code' => 'EMP-002'],
            ['email' => 'hrexecutive@hrms.local', 'code' => 'EMP-003'],
            ['email' => 'manager@hrms.local', 'code' => 'EMP-004'],
            ['email' => 'employee@hrms.local', 'code' => 'EMP-005'],
            ['email' => 'payroll@hrms.local', 'code' => 'EMP-006'],
        ];

        foreach ($users as $data) {
            $user = User::where('email', $data['email'])->first();
            if (!$user) {
                continue; // user not created yet
            }
            // Check if an employee record already exists for this email
            $exists = DB::table('employees')->where('email', $data['email'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('employees')->insert([
                'user_id' => $user->id,
                'employee_code' => $data['code'],
                'first_name' => $user->name ?? '',
                'last_name' => '',
                'email' => $data['email'],
                'phone' => '123-456-7890',
                'date_of_joining' => Carbon::now()->subMonths(rand(1, 24))->format('Y-m-d'),
                'employment_type' => 'Full-time',
                'employment_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('employees')->whereIn('email', [
            'superadmin@hrms.local',
            'hradmin@hrms.local',
            'hrexecutive@hrms.local',
            'manager@hrms.local',
            'employee@hrms.local',
            'payroll@hrms.local',
        ])->delete();
    }
};
