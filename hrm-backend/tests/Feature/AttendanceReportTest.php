<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
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
            'employee_code' => 'EMP-REP-01',
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

        // Employee 2 (unrelated, no manager)
        $this->employeeUser2 = User::factory()->create(['name' => 'Bob Independent', 'email' => 'bob@hrms.local']);
        $this->employeeUser2->assignRole('Employee');

        $this->employee2 = Employee::create([
            'user_id' => $this->employeeUser2->id,
            'employee_code' => 'EMP-REP-02',
            'first_name' => 'Bob',
            'last_name' => 'Independent',
            'email' => 'bob@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        // Create Seed Attendance Records
        // Employee 1
        Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => '2026-09-01',
            'check_in' => '2026-09-01 09:00:00',
            'check_out' => '2026-09-01 18:00:00',
            'status' => 'Present',
            'working_minutes' => 540,
            'overtime_minutes' => 60,
        ]);
        Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => '2026-09-02',
            'check_in' => '2026-09-02 09:30:00',
            'check_out' => '2026-09-02 18:00:00',
            'status' => 'Late',
            'working_minutes' => 510,
            'overtime_minutes' => 0,
        ]);
        Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => '2026-09-03',
            'check_in' => '2026-09-03 09:00:00',
            'check_out' => '2026-09-03 13:00:00',
            'status' => 'Half Day',
            'working_minutes' => 240,
            'overtime_minutes' => 0,
        ]);

        // Employee 2
        Attendance::create([
            'employee_id' => $this->employee2->id,
            'attendance_date' => '2026-09-01',
            'check_in' => '2026-09-01 09:00:00',
            'check_out' => '2026-09-01 18:00:00',
            'status' => 'Present',
            'working_minutes' => 540,
            'overtime_minutes' => 30,
        ]);

        // Manager
        Attendance::create([
            'employee_id' => $this->managerEmployee->id,
            'attendance_date' => '2026-09-01',
            'check_in' => '2026-09-01 08:50:00',
            'check_out' => '2026-09-01 18:00:00',
            'status' => 'Present',
            'working_minutes' => 550,
            'overtime_minutes' => 0,
        ]);
    }

    /** @test 1 */
    public function test_1_super_admin_can_view_company_attendance_report()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/attendance');
        $response->assertStatus(200);

        // Super Admin sees all 5 attendance records
        $this->assertEquals(5, $response->json('total'));
        $this->assertEquals(5, $response->json('summary.total_days'));
    }

    /** @test 2 */
    public function test_2_hr_admin_can_view_company_attendance_report()
    {
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/reports/attendance');
        $response->assertStatus(200);

        // HR Admin sees all 5 records
        $this->assertEquals(5, $response->json('total'));
    }

    /** @test 3 */
    public function test_3_manager_sees_only_self_and_direct_reports()
    {
        $response = $this->actingAs($this->managerUser)->getJson('/api/reports/attendance');
        $response->assertStatus(200);

        // Manager sees 3 (employee1) + 1 (manager) = 4 records, and does NOT see employee2's 1 record
        $this->assertEquals(4, $response->json('total'));

        $employeeIds = collect($response->json('data'))->pluck('employee_id')->unique()->toArray();
        $this->assertContains($this->employee1->id, $employeeIds);
        $this->assertContains($this->managerEmployee->id, $employeeIds);
        $this->assertNotContains($this->employee2->id, $employeeIds);
    }

    /** @test 4 */
    public function test_4_employee_sees_only_own_attendance()
    {
        $response = $this->actingAs($this->employeeUser1)->getJson('/api/reports/attendance');
        $response->assertStatus(200);

        // Employee 1 sees only their 3 records
        $this->assertEquals(3, $response->json('total'));

        $employeeIds = collect($response->json('data'))->pluck('employee_id')->unique()->toArray();
        $this->assertEquals([$this->employee1->id], $employeeIds);
    }

    /** @test 5 */
    public function test_5_employee_cannot_access_another_employee_attendance()
    {
        // Employee 1 attempting to query employee 2's attendance
        $response = $this->actingAs($this->employeeUser1)->getJson("/api/reports/attendance?employee_id={$this->employee2->id}");
        $response->assertStatus(403);
    }

    /** @test 6 */
    public function test_6_finance_payroll_admin_cannot_access_attendance_report()
    {
        $response = $this->actingAs($this->financeUser)->getJson('/api/reports/attendance');
        $response->assertStatus(403);
    }

    /** @test 7 */
    public function test_7_date_filtering_works_accurately()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/attendance?date_from=2026-09-02&date_to=2026-09-03');
        $response->assertStatus(200);

        // Only 2 records fall within Sep 2 - Sep 3
        $this->assertEquals(2, $response->json('total'));
    }

    /** @test 8 */
    public function test_8_invalid_date_range_rejected()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/attendance?date_from=2026-09-10&date_to=2026-09-01');
        $response->assertStatus(422)->assertJsonValidationErrors('date_to');
    }

    /** @test 9 */
    public function test_9_status_filtering_works()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/attendance?status=Late');
        $response->assertStatus(200);

        $this->assertEquals(1, $response->json('total'));
        $this->assertEquals('Late', $response->json('data.0.status'));
    }

    /** @test 10 */
    public function test_10_pagination_and_summary_calculations_are_correct()
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/attendance?per_page=2');
        $response->assertStatus(200);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(5, $response->json('total'));

        // Verify summary calculations across all 5 records
        $summary = $response->json('summary');
        $this->assertEquals(5, $summary['total_days']);
        $this->assertEquals(3, $summary['present_days']);
        $this->assertEquals(1, $summary['late_days']);
        $this->assertEquals(1, $summary['half_days']);
        $this->assertEquals(2380, $summary['total_working_minutes']); // 540 + 510 + 240 + 540 + 550
        $this->assertEquals(90, $summary['total_overtime_minutes']); // 60 + 30
    }

    /** @test 11 */
    public function test_11_csv_export_works_and_respects_rbac_scoping()
    {
        // 1. Employee export is scoped to own records
        $empExport = $this->actingAs($this->employeeUser1)->get('/api/reports/attendance/export');
        $empExport->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $empExport->headers->get('content-type'));

        // 2. Finance Admin export returns 403
        $finExport = $this->actingAs($this->financeUser)->get('/api/reports/attendance/export');
        $finExport->assertStatus(403);
    }
}
