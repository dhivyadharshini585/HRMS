<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $managerUser;
    protected User $otherManagerUser;
    protected User $employeeUser1;
    protected User $employeeUser2;

    protected Employee $managerEmployee;
    protected Employee $otherManagerEmployee;
    protected Employee $employee1;
    protected Employee $employee2;
    protected Shift $defaultShift;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $dept = Department::create(['name' => 'Tech', 'code' => 'TC']);
        $desig = Designation::create(['title' => 'Dev', 'code' => 'DEV', 'department_id' => $dept->id]);

        $this->defaultShift = Shift::create([
            'name' => 'General Shift',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'grace_period_minutes' => 15,
            'overtime_enabled' => true,
            'overtime_threshold_minutes' => 60,
        ]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();

        $this->otherManagerUser = User::create([
            'name' => 'Other Manager',
            'email' => 'othermgr@hrms.local',
            'password' => bcrypt('password'),
        ]);
        $this->otherManagerUser->assignRole('Manager');

        $this->managerEmployee = Employee::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-MGR',
            'first_name' => 'Manager',
            'last_name' => 'User',
            'email' => 'manager@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'shift_id' => $this->defaultShift->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        $this->otherManagerEmployee = Employee::create([
            'user_id' => $this->otherManagerUser->id,
            'employee_code' => 'EMP-OMGR',
            'first_name' => 'OtherManager',
            'last_name' => 'User',
            'email' => 'othermgr@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'shift_id' => $this->defaultShift->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        $this->employeeUser1 = User::create([
            'name' => 'Emp One',
            'email' => 'emp1@hrms.local',
            'password' => bcrypt('password'),
        ]);
        $this->employeeUser1->assignRole('Employee');

        $this->employee1 = Employee::create([
            'user_id' => $this->employeeUser1->id,
            'employee_code' => 'EMP-1',
            'first_name' => 'Emp',
            'last_name' => 'One',
            'email' => 'emp1@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'shift_id' => $this->defaultShift->id,
            'manager_id' => $this->managerEmployee->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        $this->employeeUser2 = User::create([
            'name' => 'Emp Two',
            'email' => 'emp2@hrms.local',
            'password' => bcrypt('password'),
        ]);
        $this->employeeUser2->assignRole('Employee');

        $this->employee2 = Employee::create([
            'user_id' => $this->employeeUser2->id,
            'employee_code' => 'EMP-2',
            'first_name' => 'Emp',
            'last_name' => 'Two',
            'email' => 'emp2@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'shift_id' => $this->defaultShift->id,
            'manager_id' => $this->otherManagerEmployee->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);
    }

    public function test_employee_can_submit_own_correction()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance-corrections', [
            'attendance_id' => $attendance->id,
            'requested_check_in' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
            'requested_check_out' => Carbon::today()->setTime(18, 0)->toDateTimeString(),
            'reason' => 'Forgot to check in',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee1->id,
            'status' => 'Pending',
        ]);
    }

    public function test_employee_cannot_submit_another_employees_correction()
    {
        $attendance2 = Attendance::create([
            'employee_id' => $this->employee2->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance-corrections', [
            'attendance_id' => $attendance2->id,
            'reason' => 'Test',
        ]);

        $response->assertStatus(404);
    }

    public function test_duplicate_pending_correction_is_blocked()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee1->id,
            'reason' => 'First try',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance-corrections', [
            'attendance_id' => $attendance->id,
            'reason' => 'Second try',
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_sees_direct_report_corrections_only()
    {
        $att1 = Attendance::create(['employee_id' => $this->employee1->id, 'attendance_date' => Carbon::today()->toDateString(), 'status' => 'Absent']);
        $att2 = Attendance::create(['employee_id' => $this->employee2->id, 'attendance_date' => Carbon::today()->toDateString(), 'status' => 'Absent']);

        AttendanceCorrection::create(['attendance_id' => $att1->id, 'employee_id' => $this->employee1->id, 'reason' => 'R1']);
        AttendanceCorrection::create(['attendance_id' => $att2->id, 'employee_id' => $this->employee2->id, 'reason' => 'R2']);

        $response = $this->actingAs($this->managerUser)->getJson('/api/attendance-corrections');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->employee1->id, $data[0]['employee_id']);
    }

    public function test_manager_can_approve_direct_report_correction_and_logic_recalculates()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee1->id,
            'requested_check_in' => Carbon::today()->setTime(9, 0)->toDateTimeString(), // Expected
            'requested_check_out' => Carbon::today()->setTime(19, 30)->toDateTimeString(), // 1.5 hours overtime
            'reason' => 'Forgot to check in',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/approve");

        $response->assertStatus(200);

        $attendance->refresh();
        $this->assertEquals('Present', $attendance->status);
        $this->assertEquals(630, $attendance->working_minutes); // 10.5 hours * 60 = 630 mins
        $this->assertEquals(90, $attendance->overtime_minutes);

        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'status' => 'Approved',
            'reviewed_by' => $this->managerUser->id,
        ]);

        // Audit log test implicitly passed if we don't crash
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance_correction.approved'
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance.updated_by_correction'
        ]);
    }

    public function test_manager_cannot_approve_another_teams_correction()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee2->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee2->id,
            'reason' => 'R1',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/approve");

        $response->assertStatus(403);
    }

    public function test_manager_can_reject_with_remarks()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee1->id,
            'reason' => 'Forgot to check in',
            'status' => 'Pending',
        ]);

        // missing remarks
        $response1 = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/reject");
        $response1->assertStatus(422);

        // with remarks
        $response2 = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/reject", [
            'remarks' => 'Not valid'
        ]);
        $response2->assertStatus(200);

        $this->assertDatabaseHas('attendance_corrections', [
            'id' => $correction->id,
            'status' => 'Rejected',
            'remarks' => 'Not valid'
        ]);

        $attendance->refresh();
        $this->assertEquals('Absent', $attendance->status);
    }

    public function test_invalid_state_transitions_are_rejected()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee1->id,
            'reason' => 'test',
            'status' => 'Approved',
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/approve");
        $response->assertStatus(422);

        $response = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/reject", ['remarks'=>'x']);
        $response->assertStatus(422);
    }
    public function test_correction_recalculates_early_exit_minutes()
    {
        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => Carbon::today()->toDateString(),
            'status' => 'Absent',
        ]);

        $correction = AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee1->id,
            'requested_check_in' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
            'requested_check_out' => Carbon::today()->setTime(17, 0)->toDateTimeString(), // 1 hour early
            'reason' => 'Forgot to check in',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/attendance-corrections/{$correction->id}/approve");

        $response->assertStatus(200);

        $attendance->refresh();
        $this->assertEquals(60, $attendance->early_exit_minutes);
    }
}
