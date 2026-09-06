<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $managerUser;
    protected User $employeeUser1;
    protected User $employeeUser2;
    protected User $financeUser;

    protected Employee $managerEmployee;
    protected Employee $employee1;
    protected Employee $employee2;

    protected LeaveType $casualLeave;
    protected LeaveType $sickLeave;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $desig = Designation::create(['title' => 'Software Engineer', 'code' => 'SE', 'department_id' => $dept->id]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->financeUser = User::where('email', 'payroll@hrms.local')->first();

        $this->casualLeave = LeaveType::firstOrCreate(
            ['name' => 'Casual Leave'],
            [
                'description' => 'Casual leave',
                'default_annual_allocation' => 12,
                'is_active' => true,
                'is_unpaid' => false,
            ]
        );
        $this->sickLeave = LeaveType::firstOrCreate(
            ['name' => 'Sick Leave'],
            [
                'description' => 'Sick leave',
                'default_annual_allocation' => 12,
                'is_active' => true,
                'is_unpaid' => false,
            ]
        );

        // Manager Employee
        $this->managerEmployee = Employee::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-MGR-01',
            'first_name' => 'Manager',
            'last_name' => 'Boss',
            'email' => 'manager@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        // Employee 1 (reports to managerEmployee)
        $this->employeeUser1 = User::factory()->create(['name' => 'Alice Employee', 'email' => 'alice@hrms.local']);
        $this->employeeUser1->assignRole('Employee');

        $this->employee1 = Employee::create([
            'user_id' => $this->employeeUser1->id,
            'employee_code' => 'EMP-LREP-01',
            'first_name' => 'Alice',
            'last_name' => 'Worker',
            'email' => 'alice@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'manager_id' => $this->managerEmployee->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        // Employee 2 (unrelated)
        $this->employeeUser2 = User::factory()->create(['name' => 'Bob Independent', 'email' => 'bob@hrms.local']);
        $this->employeeUser2->assignRole('Employee');

        $this->employee2 = Employee::create([
            'user_id' => $this->employeeUser2->id,
            'employee_code' => 'EMP-LREP-02',
            'first_name' => 'Bob',
            'last_name' => 'Independent',
            'email' => 'bob@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        // Leave Balances for Employee 1
        LeaveBalance::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'year' => 2026,
            'allocated_days' => 12,
            'used_days' => 3,
            'remaining_days' => 9,
        ]);

        // Seed Leave Requests
        // Request 1: Employee 1 Approved Casual Leave
        LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-03',
            'number_of_days' => 3,
            'reason' => 'Family vacation',
            'status' => 'Approved',
            'manager_id' => $this->managerEmployee->id,
        ]);

        // Request 2: Employee 1 Pending Sick Leave
        LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->sickLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-01',
            'number_of_days' => 1,
            'reason' => 'Doctor checkup',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        // Request 3: Employee 2 Approved Casual Leave
        LeaveRequest::create([
            'employee_id' => $this->employee2->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-10',
            'to_date' => '2026-10-12',
            'number_of_days' => 3,
            'reason' => 'Out of town',
            'status' => 'Approved',
        ]);
    }

    /** @test 1 */
    public function test_1_hr_admin_can_view_company_leave_report()
    {
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/reports/leave');
        $response->assertStatus(200);

        // HR Admin sees all 3 requests across all employees
        $this->assertEquals(3, $response->json('total'));
        $this->assertEquals(7, $response->json('summary.total_days')); // 3 + 1 + 3
    }

    /** @test 2 */
    public function test_2_manager_sees_only_self_and_direct_reports()
    {
        $response = $this->actingAs($this->managerUser)->getJson('/api/reports/leave');
        $response->assertStatus(200);

        // Manager sees employee 1's 2 requests, but NOT employee 2's request
        $this->assertEquals(2, $response->json('total'));

        $employeeIds = collect($response->json('data'))->pluck('employee_id')->unique()->toArray();
        $this->assertContains($this->employee1->id, $employeeIds);
        $this->assertNotContains($this->employee2->id, $employeeIds);
    }

    /** @test 3 */
    public function test_3_employee_sees_only_own_leave_report()
    {
        $response = $this->actingAs($this->employeeUser1)->getJson('/api/reports/leave');
        $response->assertStatus(200);

        // Employee 1 sees only their 2 requests
        $this->assertEquals(2, $response->json('total'));

        $employeeIds = collect($response->json('data'))->pluck('employee_id')->unique()->toArray();
        $this->assertEquals([$this->employee1->id], $employeeIds);
    }

    /** @test 4 */
    public function test_4_employee_cannot_access_another_employee_leave_report()
    {
        $response = $this->actingAs($this->employeeUser1)->getJson("/api/reports/leave?employee_id={$this->employee2->id}");
        $response->assertStatus(403);
    }

    /** @test 5 */
    public function test_5_finance_payroll_admin_cannot_access_leave_report()
    {
        $response = $this->actingAs($this->financeUser)->getJson('/api/reports/leave');
        $response->assertStatus(403);
    }

    /** @test 6 */
    public function test_6_date_filtering_works_accurately()
    {
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/reports/leave?date_from=2026-11-01&date_to=2026-11-05');
        $response->assertStatus(200);

        // Only 1 request falls in November
        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals($this->sickLeave->id, $response->json('data.0.leave_type_id'));
    }

    /** @test 7 */
    public function test_7_leave_type_and_status_filtering_work()
    {
        $response = $this->actingAs($this->hrAdmin)->getJson("/api/reports/leave?leave_type_id={$this->casualLeave->id}&status=Approved");
        $response->assertStatus(200);

        $this->assertEquals(2, $response->json('total'));
        foreach ($response->json('data') as $row) {
            $this->assertEquals($this->casualLeave->id, $row['leave_type_id']);
            $this->assertEquals('Approved', $row['status']);
        }
    }

    /** @test 8 */
    public function test_8_leave_balance_summary_is_correct()
    {
        // When Employee views report, balance summary for employee is provided
        $response = $this->actingAs($this->employeeUser1)->getJson('/api/reports/leave');
        $response->assertStatus(200);

        $balances = $response->json('summary.balances');
        $this->assertNotEmpty($balances);
        $this->assertEquals(12, $balances[0]['allocated_days']);
        $this->assertEquals(3, $balances[0]['used_days']);
        $this->assertEquals(9, $balances[0]['remaining_days']);
    }

    /** @test 9 */
    public function test_9_csv_export_works_and_respects_rbac_scoping()
    {
        // 1. Manager export is scoped to team
        $mgrExport = $this->actingAs($this->managerUser)->get('/api/reports/leave/export');
        $mgrExport->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $mgrExport->headers->get('content-type'));

        // 2. Finance Admin export returns 403
        $finExport = $this->actingAs($this->financeUser)->get('/api/reports/leave/export');
        $finExport->assertStatus(403);
    }
}
