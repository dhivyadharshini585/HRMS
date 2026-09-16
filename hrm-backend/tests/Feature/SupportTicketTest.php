<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected $dept;
    protected $desig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->dept = Department::create(['name' => 'IT', 'code' => 'IT']);
        $this->desig = Designation::create(['title' => 'Developer', 'code' => 'DEV', 'department_id' => $this->dept->id]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        return $user;
    }

    private function createEmployeeForUser(User $user, ?string $code = null): Employee
    {
        return Employee::create([
            'user_id' => $user->id,
            'employee_code' => $code ?? 'EMP-' . rand(10000, 99999),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'department_id' => $this->dept->id,
            'designation_id' => $this->desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);
    }

    public function test_authenticated_employee_linked_user_can_create_ticket()
    {
        $user = $this->createUserWithRole('Employee');
        $employee = $this->createEmployeeForUser($user);

        $response = $this->actingAs($user)->postJson('/api/support-tickets', [
            'problem' => 'Monitor flickers when turning on',
            'category' => 'Hardware',
            'priority' => 'High',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_tickets', [
            'id' => $response->json('id'),
            'employee_id' => $employee->id,
            'problem' => 'Monitor flickers when turning on',
            'category' => 'Hardware',
            'priority' => 'High',
            'status' => 'Open',
        ]);
    }

    public function test_created_ticket_employee_id_equals_authenticated_users_employee_id()
    {
        $user = $this->createUserWithRole('Employee');
        $employee = $this->createEmployeeForUser($user);

        $response = $this->actingAs($user)->postJson('/api/support-tickets', [
            'problem' => 'Cannot log into VPN service',
            'category' => 'Network',
            'priority' => 'Medium',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($employee->id, $response->json('employee_id'));
    }

    public function test_request_supplied_employee_id_cannot_override_authenticated_ownership()
    {
        $user1 = $this->createUserWithRole('Employee');
        $employee1 = $this->createEmployeeForUser($user1);

        $user2 = $this->createUserWithRole('Employee');
        $employee2 = $this->createEmployeeForUser($user2);

        // User 1 attempts to create a ticket passing User 2's employee_id in payload
        $response = $this->actingAs($user1)->postJson('/api/support-tickets', [
            'problem' => 'Software crashes on launch',
            'category' => 'Software',
            'priority' => 'Medium',
            'employee_id' => $employee2->id,
        ]);

        $response->assertStatus(201);
        // The created ticket MUST belong to employee1, not employee2
        $this->assertEquals($employee1->id, $response->json('employee_id'));
        $this->assertNotEquals($employee2->id, $response->json('employee_id'));
    }

    public function test_user_without_employee_relationship_receives_403()
    {
        $user = $this->createUserWithRole('Employee');
        // Do NOT create Employee record for $user

        $response = $this->actingAs($user)->postJson('/api/support-tickets', [
            'problem' => 'Password reset required urgently',
            'category' => 'Access',
            'priority' => 'Low',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'User must be linked to an employee.']);
    }

    public function test_manager_with_employee_record_can_create_ticket()
    {
        $user = $this->createUserWithRole('Manager');
        $employee = $this->createEmployeeForUser($user);

        $response = $this->actingAs($user)->postJson('/api/support-tickets', [
            'problem' => 'Requesting laptop upgrade for project',
            'category' => 'Hardware',
            'priority' => 'Low',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($employee->id, $response->json('employee_id'));
    }

    public function test_hr_admin_with_employee_record_can_create_ticket()
    {
        $user = $this->createUserWithRole('HR Admin');
        $employee = $this->createEmployeeForUser($user);

        $response = $this->actingAs($user)->postJson('/api/support-tickets', [
            'problem' => 'Need access to recruiting portal',
            'category' => 'Access',
            'priority' => 'Medium',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($employee->id, $response->json('employee_id'));
    }

    public function test_hr_executive_with_employee_record_can_create_ticket()
    {
        $user = $this->createUserWithRole('HR Executive');
        $employee = $this->createEmployeeForUser($user);

        $response = $this->actingAs($user)->postJson('/api/support-tickets', [
            'problem' => 'Printer jammed on 2nd floor',
            'category' => 'Hardware',
            'priority' => 'Low',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($employee->id, $response->json('employee_id'));
    }

    public function test_get_support_tickets_list_continues_to_work()
    {
        $user = $this->createUserWithRole('Employee');
        $employee = $this->createEmployeeForUser($user);

        SupportTicket::create([
            'employee_id' => $employee->id,
            'ticket_number' => 'IT-000001',
            'problem' => 'Test existing ticket listing',
            'category' => 'IT Support',
            'priority' => 'Low',
            'status' => 'Open',
        ]);

        $response = $this->actingAs($user)->getJson('/api/support-tickets');
        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }
}
