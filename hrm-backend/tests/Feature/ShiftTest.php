<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Models\EmployeeShiftRotation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $managerUser;
    protected User $employeeUser;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $dept = Department::create(['name' => 'IT', 'code' => 'IT01']);
        $desig = Designation::create(['title' => 'Dev', 'code' => 'DEV', 'department_id' => $dept->id]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();

        $this->employee = Employee::create([
            'user_id' => $this->employeeUser->id,
            'employee_code' => 'EMP-SHIFT-01',
            'first_name' => 'Shift',
            'last_name' => 'Tester',
            'email' => 'employee@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);
    }

    /** @test */
    public function test_1_super_admin_can_list_shifts()
    {
        Shift::create(['name' => 'Morning Shift', 'start_time' => '08:00', 'end_time' => '16:00']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/shifts');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    /** @test */
    public function test_2_hr_admin_can_create_a_shift_with_grace_period_and_overtime()
    {
        $payload = [
            'name' => 'Day Shift',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_active' => true,
            'description' => 'Standard working hours',
            'grace_period_minutes' => 15,
            'overtime_enabled' => true,
            'overtime_threshold_minutes' => 60,
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/shifts', $payload);
        $response->assertStatus(201)->assertJsonFragment(['name' => 'Day Shift', 'grace_period_minutes' => 15]);

        $this->assertDatabaseHas('shifts', ['name' => 'Day Shift', 'grace_period_minutes' => 15, 'overtime_enabled' => true]);
    }

    /** @test */
    public function test_3_hr_admin_can_update_a_shift()
    {
        $shift = Shift::create(['name' => 'Night Shift', 'start_time' => '22:00', 'end_time' => '06:00']);

        $updatePayload = [
            'name' => 'Night Shift Updated',
            'start_time' => '21:00',
            'end_time' => '05:00',
            'is_active' => true,
            'grace_period_minutes' => 20,
        ];

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/shifts/{$shift->id}", $updatePayload);
        $response->assertStatus(200)->assertJsonFragment(['name' => 'Night Shift Updated', 'grace_period_minutes' => 20]);
    }

    /** @test */
    public function test_4_unauthorized_user_cannot_manage_shifts()
    {
        $shift = Shift::create(['name' => 'Evening Shift', 'start_time' => '16:00', 'end_time' => '00:00']);

        // Employee GET /api/shifts returns 403
        $responseEmpGet = $this->actingAs($this->employeeUser)->getJson('/api/shifts');
        $responseEmpGet->assertStatus(403);

        // Employee POST returns 403
        $responseEmpPost = $this->actingAs($this->employeeUser)->postJson('/api/shifts', ['name' => 'Hack']);
        $responseEmpPost->assertStatus(403);

        // Employee DELETE returns 403
        $responseEmpDelete = $this->actingAs($this->employeeUser)->deleteJson("/api/shifts/{$shift->id}");
        $responseEmpDelete->assertStatus(403);
    }

    /** @test */
    public function test_5_duplicate_shift_name_is_rejected()
    {
        Shift::create(['name' => 'Rotational Shift', 'start_time' => '08:00', 'end_time' => '16:00']);

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/shifts', [
            'name' => 'Rotational Shift',
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('name');
    }

    /** @test */
    public function test_6_employee_cannot_modify_shift_assignments()
    {
        $shift = Shift::create(['name' => 'General Shift', 'start_time' => '09:00', 'end_time' => '17:00']);

        $response = $this->actingAs($this->employeeUser)->postJson("/api/employees/{$this->employee->id}/assign-shift", [
            'shift_id' => $shift->id,
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_7_shift_assignment_to_an_employee_works_for_authorized_users()
    {
        $shift = Shift::create(['name' => 'Flexi Shift', 'start_time' => '10:00', 'end_time' => '18:00']);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/employees/{$this->employee->id}/assign-shift", [
            'shift_id' => $shift->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', [
            'id' => $this->employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    /** @test */
    public function test_8_shift_rotation_creation_and_overlap_prevention()
    {
        $shift1 = Shift::create(['name' => 'Shift A', 'start_time' => '09:00', 'end_time' => '17:00']);
        $shift2 = Shift::create(['name' => 'Shift B', 'start_time' => '14:00', 'end_time' => '22:00']);

        // Create first rotation
        $response1 = $this->actingAs($this->hrAdmin)->postJson("/api/employees/{$this->employee->id}/shift-rotations", [
            'shift_id' => $shift1->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-15',
        ]);
        $response1->assertStatus(201);

        // Attempt overlapping rotation
        $response2 = $this->actingAs($this->hrAdmin)->postJson("/api/employees/{$this->employee->id}/shift-rotations", [
            'shift_id' => $shift2->id,
            'start_date' => '2026-09-10',
            'end_date' => '2026-09-20',
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors('date_range');

        // Audit log test for rotation
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shift.rotation_created',
            'entity_type' => EmployeeShiftRotation::class,
        ]);
    }

    /** @test */
    public function test_9_shift_changes_create_audit_logs()
    {
        // Create Shift
        $response = $this->actingAs($this->superAdmin)->postJson('/api/shifts', [
            'name' => 'Audit Shift',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $shiftId = $response->json('id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shift.created',
            'entity_type' => Shift::class,
            'entity_id' => $shiftId,
        ]);

        // Delete Shift
        $this->actingAs($this->superAdmin)->deleteJson("/api/shifts/{$shiftId}");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'shift.deleted',
            'entity_type' => Shift::class,
            'entity_id' => $shiftId,
        ]);
    }
}
