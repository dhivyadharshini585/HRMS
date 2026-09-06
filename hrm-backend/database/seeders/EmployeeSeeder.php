<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\User;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Departments
        $engineering = Department::firstOrCreate(['name' => 'Engineering'], ['description' => 'Software development and IT']);
        $hr = Department::firstOrCreate(['name' => 'Human Resources'], ['description' => 'HR, recruitment, and employee relations']);
        $finance = Department::firstOrCreate(['name' => 'Finance'], ['description' => 'Accounting and payroll']);
        $management = Department::firstOrCreate(['name' => 'Management'], ['description' => 'Executive management']);

        // 2. Create Designations
        $se = Designation::firstOrCreate(['title' => 'Software Engineer']);
        $hrAdmin = Designation::firstOrCreate(['title' => 'HR Manager']);
        $hrExec = Designation::firstOrCreate(['title' => 'HR Executive']);
        $accountant = Designation::firstOrCreate(['title' => 'Accountant']);
        $engManager = Designation::firstOrCreate(['title' => 'Engineering Manager']);
        $director = Designation::firstOrCreate(['title' => 'Director']);

        // 3. Pass 1: Create or Update Employee Records (with trashed resolution)
        $emp1 = $this->seedEmployee('EMP-001', 'superadmin@hrms.local', 'Super', 'Admin', $management->id, $director->id, 'Executive', 'Headquarters', 'Day Shift (9 AM - 5 PM)');
        $emp2 = $this->seedEmployee('EMP-002', 'hradmin@hrms.local', 'HR', 'Admin', $hr->id, $hrAdmin->id, 'Senior', 'Headquarters', 'Day Shift (9 AM - 5 PM)');
        $emp3 = $this->seedEmployee('EMP-003', 'hrexecutive@hrms.local', 'HR', 'Executive', $hr->id, $hrExec->id, 'Mid-Level', 'Headquarters', 'Day Shift (9 AM - 5 PM)');
        $emp4 = $this->seedEmployee('EMP-004', 'manager@hrms.local', 'Manager', 'User', $engineering->id, $engManager->id, 'Lead / Manager', 'Headquarters', 'Day Shift (9 AM - 5 PM)');
        $emp5 = $this->seedEmployee('EMP-005', 'employee@hrms.local', 'Employee', 'User', $engineering->id, $se->id, 'Junior / Mid', 'Building B / Remote', 'Flexible Shift');
        $emp6 = $this->seedEmployee('EMP-006', 'payroll@hrms.local', 'Payroll', 'Admin', $finance->id, $accountant->id, 'Senior', 'Headquarters', 'Day Shift (9 AM - 5 PM)');

        // 4. Pass 2: Assign Manager relationships safely after all employee records exist
        if ($emp2 && $emp1) $emp2->update(['manager_id' => $emp1->id]);
        if ($emp3 && $emp2) $emp3->update(['manager_id' => $emp2->id]);
        if ($emp4 && $emp1) $emp4->update(['manager_id' => $emp1->id]);
        if ($emp5 && $emp4) $emp5->update(['manager_id' => $emp4->id]);
        if ($emp6 && $emp1) $emp6->update(['manager_id' => $emp1->id]);

        // 5. Pass 3: Create Employment Histories idempotently
        if ($emp5) {
            EmploymentHistory::firstOrCreate(
                [
                    'employee_id' => $emp5->id,
                    'company_name' => 'Acme Tech Solutions',
                    'job_title' => 'Junior Developer',
                ],
                [
                    'start_date' => '2023-01-15',
                    'end_date' => '2024-06-30',
                    'responsibilities' => 'Developed web applications using React and Laravel, wrote unit tests, and resolved API bugs.',
                ]
            );

            EmploymentHistory::firstOrCreate(
                [
                    'employee_id' => $emp5->id,
                    'company_name' => 'Global Soft Inc.',
                    'job_title' => 'Software Intern',
                ],
                [
                    'start_date' => '2022-06-01',
                    'end_date' => '2022-12-31',
                    'responsibilities' => 'Assisted senior developers with frontend bug fixes and documentation.',
                ]
            );
        }

        if ($emp4) {
            EmploymentHistory::firstOrCreate(
                [
                    'employee_id' => $emp4->id,
                    'company_name' => 'Innovate Corp',
                    'job_title' => 'Senior Software Engineer',
                ],
                [
                    'start_date' => '2020-03-01',
                    'end_date' => '2023-02-28',
                    'responsibilities' => 'Led backend architecture team, managed microservices, and mentored junior engineers.',
                ]
            );
        }
    }

    private function seedEmployee($code, $email, $firstName, $lastName, $deptId, $desigId, $jobLevel, $location, $shift): ?Employee
    {
        $user = User::where('email', $email)->first();
        $userId = $user ? $user->id : null;

        // Search including soft-deleted employee records
        $employee = Employee::withTrashed()
            ->where('employee_code', $code)
            ->orWhere('email', $email)
            ->first();

        $attributes = [
            'employee_code' => $code,
            'email' => $email,
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '123-456-7890',
            'date_of_birth' => '1995-05-15',
            'gender' => 'Male',
            'date_of_joining' => '2024-01-10',
            'department_id' => $deptId,
            'designation_id' => $desigId,
            'job_level' => $jobLevel,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'work_location' => $location,
            'work_shift' => $shift,
            'address' => '123 Tech Park Way',
            'city' => 'San Jose',
            'state' => 'CA',
            'country' => 'USA',
            'postal_code' => '95110',
            'emergency_contact_name' => 'Jane Doe',
            'emergency_contact_relationship' => 'Spouse',
            'emergency_contact_phone' => '987-654-3210',
        ];

        if ($employee) {
            if ($employee->trashed()) {
                $employee->restore();
            }
            $employee->update($attributes);
            return $employee->fresh();
        }

        return Employee::create($attributes);
    }
}
