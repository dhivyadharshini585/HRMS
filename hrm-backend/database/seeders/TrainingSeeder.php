<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Training;
use App\Models\Employee;

class TrainingSeeder extends Seeder
{
    public function run(): void
    {
        $trainer1 = Employee::where('employee_code', 'EMP-004')->first();
        $trainer2 = Employee::where('employee_code', 'EMP-002')->first();

        Training::firstOrCreate(
            ['training_name' => 'Advanced React Patterns'],
            [
                'trainer_employee_id' => $trainer1 ? $trainer1->id : null,
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-15',
                'status' => 'completed'
            ]
        );

        Training::firstOrCreate(
            ['training_name' => 'HR Compliance and Policies'],
            [
                'trainer_employee_id' => $trainer2 ? $trainer2->id : null,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-05',
                'status' => 'scheduled'
            ]
        );
    }
}
