<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Project;
use App\Models\Timesheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class Phase6RBACTest extends TestCase
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

    private function createEmployeeForUser(User $user, ?int $managerId = null, ?string $code = null): Employee
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
            'manager_id' => $managerId,
        ]);
    }

    // --- PERMISSIONS TESTS (1, 2, 3) ---

    public function test_projects_view_permission_exists()
    {
        $this->assertTrue(Permission::where('name', 'projects.view')->exists());
    }

    public function test_projects_manage_permission_exists()
    {
        $this->assertTrue(Permission::where('name', 'projects.manage')->exists());
    }

    public function test_timesheets_approve_permission_exists()
    {
        $this->assertTrue(Permission::where('name', 'timesheets.approve')->exists());
    }

    // --- PROJECT RBAC TESTS (4, 5, 6, 7, 8) ---

    public function test_super_admin_can_view_and_manage_projects()
    {
        $adminUser = $this->createUserWithRole('Super Admin');

        $responseCreate = $this->actingAs($adminUser)->postJson('/api/projects', [
            'name' => 'Admin Project',
            'status' => 'Active',
        ]);
        $responseCreate->assertStatus(201);
        $projectId = $responseCreate->json('id');

        $responseIndex = $this->actingAs($adminUser)->getJson('/api/projects');
        $responseIndex->assertStatus(200);

        $responseShow = $this->actingAs($adminUser)->getJson('/api/projects/' . $projectId);
        $responseShow->assertStatus(200);
    }

    public function test_hr_admin_can_view_and_manage_projects()
    {
        $hrAdminUser = $this->createUserWithRole('HR Admin');

        $responseCreate = $this->actingAs($hrAdminUser)->postJson('/api/projects', [
            'name' => 'HR Project',
            'status' => 'Active',
        ]);
        $responseCreate->assertStatus(201);
        $projectId = $responseCreate->json('id');

        $responseIndex = $this->actingAs($hrAdminUser)->getJson('/api/projects');
        $responseIndex->assertStatus(200);

        $responseShow = $this->actingAs($hrAdminUser)->getJson('/api/projects/' . $projectId);
        $responseShow->assertStatus(200);
    }

    public function test_manager_can_view_and_manage_own_projects()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmp = $this->createEmployeeForUser($managerUser);

        $project = Project::create([
            'name' => 'Manager Project',
            'manager_id' => $managerEmp->id,
            'status' => 'Active',
        ]);

        $responseIndex = $this->actingAs($managerUser)->getJson('/api/projects');
        $responseIndex->assertStatus(200);
        $returnedIds = collect($responseIndex->json('data'))->pluck('id')->toArray();
        $this->assertContains($project->id, $returnedIds);

        $responseShow = $this->actingAs($managerUser)->getJson('/api/projects/' . $project->id);
        $responseShow->assertStatus(200);
    }

    public function test_manager_cannot_access_another_managers_project()
    {
        $managerUser1 = $this->createUserWithRole('Manager');
        $managerEmp1 = $this->createEmployeeForUser($managerUser1);

        $managerUser2 = $this->createUserWithRole('Manager');
        $managerEmp2 = $this->createEmployeeForUser($managerUser2);

        $project1 = Project::create([
            'name' => 'Manager 1 Project',
            'manager_id' => $managerEmp1->id,
            'status' => 'Active',
        ]);

        $responseShow = $this->actingAs($managerUser2)->getJson('/api/projects/' . $project1->id);
        $responseShow->assertStatus(403);

        $responseUpdate = $this->actingAs($managerUser2)->putJson('/api/projects/' . $project1->id, [
            'name' => 'Hacked Project Name',
        ]);
        $responseUpdate->assertStatus(403);
    }

    public function test_unauthorized_roles_receive_403_for_projects()
    {
        $employeeUser = $this->createUserWithRole('Employee');
        $this->createEmployeeForUser($employeeUser);

        $responseIndex = $this->actingAs($employeeUser)->getJson('/api/projects');
        $responseIndex->assertStatus(403);

        $hrExecUser = $this->createUserWithRole('HR Executive');
        $responseIndexHrExec = $this->actingAs($hrExecUser)->getJson('/api/projects');
        $responseIndexHrExec->assertStatus(403);

        $financeUser = $this->createUserWithRole('Finance/Payroll Admin');
        $responseIndexFinance = $this->actingAs($financeUser)->getJson('/api/projects');
        $responseIndexFinance->assertStatus(403);
    }

    // --- TIMESHEET RBAC TESTS (9 - 18) ---

    public function test_super_admin_can_view_all_timesheets_and_approve()
    {
        $adminUser = $this->createUserWithRole('Super Admin');

        $empUser = $this->createUserWithRole('Employee');
        $emp = $this->createEmployeeForUser($empUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);
        $timesheet = Timesheet::create([
            'employee_id' => $emp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseIndex = $this->actingAs($adminUser)->getJson('/api/timesheets');
        $responseIndex->assertStatus(200);

        $responseApprove = $this->actingAs($adminUser)->postJson('/api/timesheets/' . $timesheet->id . '/approve');
        $responseApprove->assertStatus(200);
        $this->assertEquals('Approved', $timesheet->fresh()->status);
    }

    public function test_hr_admin_can_view_all_timesheets_and_approve()
    {
        $hrAdminUser = $this->createUserWithRole('HR Admin');

        $empUser = $this->createUserWithRole('Employee');
        $emp = $this->createEmployeeForUser($empUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);
        $timesheet = Timesheet::create([
            'employee_id' => $emp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseIndex = $this->actingAs($hrAdminUser)->getJson('/api/timesheets');
        $responseIndex->assertStatus(200);

        $responseApprove = $this->actingAs($hrAdminUser)->postJson('/api/timesheets/' . $timesheet->id . '/approve');
        $responseApprove->assertStatus(200);
        $this->assertEquals('Approved', $timesheet->fresh()->status);
    }

    public function test_manager_can_view_own_and_direct_reports_timesheets()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmp = $this->createEmployeeForUser($managerUser);

        $drUser = $this->createUserWithRole('Employee');
        $drEmp = $this->createEmployeeForUser($drUser, $managerEmp->id);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $tsMgr = Timesheet::create([
            'employee_id' => $managerEmp->id,
            'project_id' => $project->id,
            'date' => '2026-09-14',
            'hours' => 8,
            'status' => 'Draft',
        ]);

        $tsDR = Timesheet::create([
            'employee_id' => $drEmp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseIndex = $this->actingAs($managerUser)->getJson('/api/timesheets');
        $responseIndex->assertStatus(200);
        $returnedIds = collect($responseIndex->json('data'))->pluck('id')->toArray();

        $this->assertContains($tsMgr->id, $returnedIds);
        $this->assertContains($tsDR->id, $returnedIds);
    }

    public function test_manager_cannot_view_unrelated_employees_timesheets()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmp = $this->createEmployeeForUser($managerUser);

        $otherUser = $this->createUserWithRole('Employee');
        $otherEmp = $this->createEmployeeForUser($otherUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $tsOther = Timesheet::create([
            'employee_id' => $otherEmp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseShow = $this->actingAs($managerUser)->getJson('/api/timesheets/' . $tsOther->id);
        $responseShow->assertStatus(403);
    }

    public function test_manager_can_approve_direct_report_submitted_timesheet()
    {
        $managerUser = $this->createUserWithRole('Manager');
        $managerEmp = $this->createEmployeeForUser($managerUser);

        $drUser = $this->createUserWithRole('Employee');
        $drEmp = $this->createEmployeeForUser($drUser, $managerEmp->id);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $tsDR = Timesheet::create([
            'employee_id' => $drEmp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseApprove = $this->actingAs($managerUser)->postJson('/api/timesheets/' . $tsDR->id . '/approve');
        $responseApprove->assertStatus(200);
        $this->assertEquals('Approved', $tsDR->fresh()->status);
    }

    public function test_employee_can_view_own_timesheet()
    {
        $empUser = $this->createUserWithRole('Employee');
        $emp = $this->createEmployeeForUser($empUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $ts = Timesheet::create([
            'employee_id' => $emp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Draft',
        ]);

        $responseShow = $this->actingAs($empUser)->getJson('/api/timesheets/' . $ts->id);
        $responseShow->assertStatus(200);
    }

    public function test_employee_cannot_view_another_employees_timesheet()
    {
        $empUser1 = $this->createUserWithRole('Employee');
        $emp1 = $this->createEmployeeForUser($empUser1);

        $empUser2 = $this->createUserWithRole('Employee');
        $emp2 = $this->createEmployeeForUser($empUser2);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $ts2 = Timesheet::create([
            'employee_id' => $emp2->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Draft',
        ]);

        $responseShow = $this->actingAs($empUser1)->getJson('/api/timesheets/' . $ts2->id);
        $responseShow->assertStatus(403);
    }

    public function test_employee_cannot_approve_timesheet()
    {
        $empUser = $this->createUserWithRole('Employee');
        $emp = $this->createEmployeeForUser($empUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $ts = Timesheet::create([
            'employee_id' => $emp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseApprove = $this->actingAs($empUser)->postJson('/api/timesheets/' . $ts->id . '/approve');
        $responseApprove->assertStatus(403);
    }

    public function test_finance_payroll_admin_cannot_approve_timesheet()
    {
        $finUser = $this->createUserWithRole('Finance/Payroll Admin');

        $empUser = $this->createUserWithRole('Employee');
        $emp = $this->createEmployeeForUser($empUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $ts = Timesheet::create([
            'employee_id' => $emp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseApprove = $this->actingAs($finUser)->postJson('/api/timesheets/' . $ts->id . '/approve');
        $responseApprove->assertStatus(403);
    }

    public function test_hr_executive_cannot_approve_timesheet()
    {
        $hrExecUser = $this->createUserWithRole('HR Executive');

        $empUser = $this->createUserWithRole('Employee');
        $emp = $this->createEmployeeForUser($empUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $ts = Timesheet::create([
            'employee_id' => $emp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Submitted',
        ]);

        $responseApprove = $this->actingAs($hrExecUser)->postJson('/api/timesheets/' . $ts->id . '/approve');
        $responseApprove->assertStatus(403);
    }

    public function test_hr_executive_has_timesheets_view_permission()
    {
        $hrExecUser = $this->createUserWithRole('HR Executive');
        $this->assertTrue($hrExecUser->hasPermissionTo('timesheets.view'));
        $this->assertFalse($hrExecUser->hasPermissionTo('timesheets.approve'));
    }

    public function test_hr_executive_can_view_own_timesheet()
    {
        $hrExecUser = $this->createUserWithRole('HR Executive');
        $hrExecEmp = $this->createEmployeeForUser($hrExecUser);

        $project = Project::create(['name' => 'P1', 'status' => 'Active']);

        $ts = Timesheet::create([
            'employee_id' => $hrExecEmp->id,
            'project_id' => $project->id,
            'date' => '2026-09-15',
            'hours' => 8,
            'status' => 'Draft',
        ]);

        $responseIndex = $this->actingAs($hrExecUser)->getJson('/api/timesheets');
        $responseIndex->assertStatus(200);

        $responseShow = $this->actingAs($hrExecUser)->getJson('/api/timesheets/' . $ts->id);
        $responseShow->assertStatus(200);
    }
}
