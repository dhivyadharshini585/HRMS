<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TrainingAttendee;
use App\Models\Training;
use App\Models\Employee;

class TrainingAttendeeSeeder extends Seeder
{
    public function run(): void
    {
        $training1 = Training::where('training_name', 'Advanced React Patterns')->first();
        $training2 = Training::where('training_name', 'HR Compliance and Policies')->first();

        $emp5 = Employee::where('employee_code', 'EMP-005')->first();
        $emp3 = Employee::where('employee_code', 'EMP-003')->first();
        $emp6 = Employee::where('employee_code', 'EMP-006')->first();

        if ($training1 && $emp5) {
            TrainingAttendee::firstOrCreate(
                ['training_id' => $training1->id, 'employee_id' => $emp5->id],
                ['completion_status' => 'completed']
            );
        }

        if ($training2) {
            if ($emp3) {
                TrainingAttendee::firstOrCreate(
                    ['training_id' => $training2->id, 'employee_id' => $emp3->id],
                    ['completion_status' => 'enrolled']
                );
            }
            if ($emp6) {
                TrainingAttendee::firstOrCreate(
                    ['training_id' => $training2->id, 'employee_id' => $emp6->id],
                    ['completion_status' => 'enrolled']
                );
            }
        }
    }
}
