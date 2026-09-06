<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $managerUser;
    protected User $employeeUser1;
    protected User $employeeUser2;

    protected Employee $employee1;
    protected Employee $employee2;
    protected Employee $managerEmployee;
    protected Shift $defaultShift;
    protected Shift $nightShift;

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

        $this->nightShift = Shift::create([
            'name' => 'Night Shift',
            'start_time' => '22:00',
            'end_time' => '07:00',
            'grace_period_minutes' => 15,
            'overtime_enabled' => true,
            'overtime_threshold_minutes' => 60,
        ]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();

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

        $this->employeeUser1 = User::factory()->create(['name' => 'Emp One', 'email' => 'emp1@hrms.local']);
        $this->employeeUser1->assignRole('Employee');

        $this->employee1 = Employee::create([
            'user_id' => $this->employeeUser1->id,
            'employee_code' => 'EMP-101',
            'first_name' => 'Employee',
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

        $this->employeeUser2 = User::factory()->create(['name' => 'Emp Two', 'email' => 'emp2@hrms.local']);
        $this->employeeUser2->assignRole('Employee');

        $this->employee2 = Employee::create([
            'user_id' => $this->employeeUser2->id,
            'employee_code' => 'EMP-102',
            'first_name' => 'Employee',
            'last_name' => 'Two',
            'email' => 'emp2@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'shift_id' => $this->defaultShift->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);
    }

    /** @test */
    public function test_1_super_admin_can_view_attendance()
    {
        Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => now()->toDateString(),
            'check_in' => now(),
            'status' => 'Present',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/attendance');
        $response->assertStatus(200)->assertJsonStructure(['data', 'total']);
    }

    /** @test */
    public function test_2_hr_admin_can_view_attendance()
    {
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/attendance');
        $response->assertStatus(200);
    }

    /** @test */
    public function test_3_employee_can_view_own_attendance()
    {
        Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => now()->toDateString(),
            'check_in' => now(),
            'status' => 'Present',
        ]);

        $response = $this->actingAs($this->employeeUser1)->getJson('/api/attendance');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->employee1->id, $data[0]['employee_id']);
    }

    /** @test */
    public function test_4_employee_cannot_view_another_employees_attendance()
    {
        Attendance::create([
            'employee_id' => $this->employee2->id,
            'attendance_date' => now()->toDateString(),
            'check_in' => now(),
            'status' => 'Present',
        ]);

        $response = $this->actingAs($this->employeeUser1)->getJson('/api/attendance');
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    /** @test */
    public function test_5_employee_can_check_in()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in');
        $response->assertStatus(201)->assertJsonStructure(['message', 'attendance']);

        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee1->id,
            'attendance_date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function test_6_employee_cannot_check_in_twice_on_same_day()
    {
        $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in');
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in');

        $response->assertStatus(422)->assertJson(['message' => 'Already checked in for today.']);
    }

    /** @test */
    public function test_7_employee_can_check_out_after_checking_in()
    {
        $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in');

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-out');
        $response->assertStatus(200)->assertJsonStructure(['message', 'attendance']);
    }

    /** @test */
    public function test_8_employee_cannot_check_out_before_checking_in()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-out');
        $response->assertStatus(422)->assertJson(['message' => 'Cannot check out before checking in.']);
    }

    /** @test */
    public function test_9_employee_cannot_check_out_twice()
    {
        $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in');
        $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-out');

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-out');
        $response->assertStatus(422)->assertJson(['message' => 'Already checked out for today.']);
    }

    /** @test */
    public function test_10_checkout_calculates_working_duration_and_status_correctly()
    {
        $shift = $this->employee1->currentShift();
        $dateStr = now()->toDateString();
        $checkInTime = Carbon::parse($dateStr . ' ' . ($shift ? $shift->start_time : '09:00:00'));
        Carbon::setTestNow($checkInTime->copy()->addHours(5));

        $attendance = Attendance::create([
            'employee_id' => $this->employee1->id,
            'attendance_date' => $dateStr,
            'check_in' => $checkInTime,
            'status' => 'Present',
        ]);

        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-out');
        $response->assertStatus(200);

        $attendance->refresh();
        $this->assertGreaterThanOrEqual(299, $attendance->working_minutes);
        $this->assertEquals('Present', $attendance->status);

        Carbon::setTestNow(); // Reset mock time
    }

    /** @test */
    public function test_11_unauthenticated_user_cannot_access_attendance()
    {
        $response = $this->getJson('/api/attendance');
        $response->assertStatus(401);
    }

    /** @test */
    public function test_12_attendance_actions_create_correct_audit_logs()
    {
        $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance.check_in',
            'entity_type' => Attendance::class,
        ]);

        $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-out');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'attendance.check_out',
            'entity_type' => Attendance::class,
        ]);
    }

    /** @test */
    public function test_13_employee_cannot_manipulate_another_employee_id_through_request()
    {
        // Try passing employee2's id in check-in request payload as employeeUser1
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/attendance/check-in', [
            'employee_id' => $this->employee2->id,
        ]);

        $response->assertStatus(201);

        // Verify the check-in was recorded for employee1, NOT employee2
        $this->assertDatabaseHas('attendances', [
            'employee_id' => $this->employee1->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'employee_id' => $this->employee2->id,
        ]);
    }

    /** @test */
    public function test_14_night_shift_crossing_midnight_and_grace_period_calculation()
    {
        // Assign Night Shift (22:00 - 07:00) to employee 2
        $this->employee2->update(['shift_id' => $this->nightShift->id]);

        // Simulating check-in at 22:10 (within 15 min grace period)
        Carbon::setTestNow('2026-09-04 22:10:00');
        $response = $this->actingAs($this->employeeUser2)->postJson('/api/attendance/check-in');
        $response->assertStatus(201);

        $attendance = Attendance::where('employee_id', $this->employee2->id)->first();
        $this->assertEquals('Present', $attendance->status);
        $this->assertEquals('2026-09-04', Carbon::parse($attendance->attendance_date)->toDateString());

        // Simulating check-out next morning at 07:30 (with 30 min overtime, threshold 60)
        Carbon::setTestNow('2026-09-05 07:30:00');
        $responseOut = $this->actingAs($this->employeeUser2)->postJson('/api/attendance/check-out');
        $responseOut->assertStatus(200);

        Carbon::setTestNow(); // reset time
    }
}
