<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

class EmployeeRBACTest extends TestCase
{
    use RefreshDatabase;

    protected $dept;
    protected $desig;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the existing seeder to set up roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->dept = Department::create(['name' => 'IT', 'code' => 'IT']);
        $this->desig = Designation::create(['title' => 'Developer', 'code' => 'DEV', 'department_id' => $this->dept->id]);
    }

    private function createUserWithRole($roleName)
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        return $user;
    }

    private function createEmployeeForUser($user, $managerId = null, $code = null)
    {
        return Employee::create([
            'user_id' => $user->id,
            'employee_code' => $code ?? 'EMP-' . rand(1000, 9999),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'department_id' => $this->dept->id,
            'designation_id' => $this->desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
            'manager_id' => $managerId,
        ]);
    }

    public function test_manager_can_list_own_team_but_not_others()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmployee = $this->createEmployeeForUser($managerUser, null, 'EMP-MGR');

        $directReportUser = $this->createUserWithRole('Employee');
        $directReport = $this->createEmployeeForUser($directReportUser, $managerEmployee->id, 'EMP-DR1');

        $otherUser = $this->createUserWithRole('Employee');
        $otherEmployee = $this->createEmployeeForUser($otherUser, null, 'EMP-OTH1');

        $response = $this->actingAs($managerUser)->getJson('/api/employees');

        $response->assertStatus(200);
        $data = $response->json('data');

        $returnedIds = collect($data)->pluck('id')->toArray();

        $this->assertContains($managerEmployee->id, $returnedIds, "Manager should see themselves");
        $this->assertContains($directReport->id, $returnedIds, "Manager should see direct report");
        $this->assertNotContains($otherEmployee->id, $returnedIds, "Manager should not see other employees");
    }

    public function test_manager_can_view_direct_report_profile()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmployee = $this->createEmployeeForUser($managerUser);

        $directReportUser = $this->createUserWithRole('Employee');
        $directReport = $this->createEmployeeForUser($directReportUser, $managerEmployee->id);

        $response = $this->actingAs($managerUser)->getJson('/api/employees/' . $directReport->id);

        $response->assertStatus(200);
        $this->assertEquals($directReport->id, $response->json('id'));
    }

    public function test_manager_cannot_view_other_employee_profile()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmployee = $this->createEmployeeForUser($managerUser);

        $otherUser = $this->createUserWithRole('Employee');
        $otherEmployee = $this->createEmployeeForUser($otherUser);

        $response = $this->actingAs($managerUser)->getJson('/api/employees/' . $otherEmployee->id);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_all_employees()
    {
        $adminUser = $this->createUserWithRole('Super Admin');

        $otherUser = $this->createUserWithRole('Employee');
        $otherEmployee = $this->createEmployeeForUser($otherUser);

        $responseList = $this->actingAs($adminUser)->getJson('/api/employees');
        $responseList->assertStatus(200);

        $returnedIds = collect($responseList->json('data'))->pluck('id')->toArray();
        $this->assertContains($otherEmployee->id, $returnedIds);

        $responseShow = $this->actingAs($adminUser)->getJson('/api/employees/' . $otherEmployee->id);
        $responseShow->assertStatus(200);
    }

    public function test_regular_employee_can_only_view_self()
    {
        $employeeUser = $this->createUserWithRole('Employee');
        $employee = $this->createEmployeeForUser($employeeUser);

        $otherUser = $this->createUserWithRole('Employee');
        $otherEmployee = $this->createEmployeeForUser($otherUser);

        // Should be forbidden from index route due to route middleware missing permissions
        $responseList = $this->actingAs($employeeUser)->getJson('/api/employees');
        $responseList->assertStatus(403);

        // Should be able to view self
        $responseShowSelf = $this->actingAs($employeeUser)->getJson('/api/employees/' . $employee->id);
        $responseShowSelf->assertStatus(200);

        // Should NOT be able to view other
        $responseShowOther = $this->actingAs($employeeUser)->getJson('/api/employees/' . $otherEmployee->id);
        $responseShowOther->assertStatus(403);
    }
}
