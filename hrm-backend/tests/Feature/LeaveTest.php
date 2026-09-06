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

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $managerUser;
    protected User $employeeUser1;
    protected User $employeeUser2;

    protected Employee $managerEmployee;
    protected Employee $employee1;
    protected Employee $employee2;

    protected LeaveType $casualLeave;
    protected LeaveType $sickLeave;
    protected LeaveType $unpaidLeave;
    protected LeaveType $compOff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        // Seed leave types
        $this->seedLeaveTypes();

        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $desig = Designation::create(['title' => 'Software Engineer', 'code' => 'SE', 'department_id' => $dept->id]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();

        // Create Manager Employee
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

        // Create Employee 1 (reports to managerEmployee)
        $this->employeeUser1 = User::factory()->create(['name' => 'Alice Employee', 'email' => 'alice@hrms.local']);
        $this->employeeUser1->assignRole('Employee');

        $this->employee1 = Employee::create([
            'user_id' => $this->employeeUser1->id,
            'employee_code' => 'EMP-LEAVE-01',
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

        // Create Employee 2 (unrelated, no manager or different manager)
        $this->employeeUser2 = User::factory()->create(['name' => 'Bob Independent', 'email' => 'bob@hrms.local']);
        $this->employeeUser2->assignRole('Employee');

        $this->employee2 = Employee::create([
            'user_id' => $this->employeeUser2->id,
            'employee_code' => 'EMP-LEAVE-02',
            'first_name' => 'Bob',
            'last_name' => 'Independent',
            'email' => 'bob@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        // Create balances for employee1 & employee2
        $this->createBalancesForEmployee($this->employee1);
        $this->createBalancesForEmployee($this->employee2);
    }

    protected function seedLeaveTypes()
    {
        $types = [
            ['name' => 'Casual Leave', 'default_annual_allocation' => 12, 'is_unpaid' => false],
            ['name' => 'Sick Leave', 'default_annual_allocation' => 12, 'is_unpaid' => false],
            ['name' => 'Earned Leave', 'default_annual_allocation' => 15, 'is_unpaid' => false],
            ['name' => 'Paid Leave', 'default_annual_allocation' => 10, 'is_unpaid' => false],
            ['name' => 'Unpaid Leave', 'default_annual_allocation' => 0, 'is_unpaid' => true],
            ['name' => 'Maternity Leave', 'default_annual_allocation' => 180, 'is_unpaid' => false],
            ['name' => 'Paternity Leave', 'default_annual_allocation' => 15, 'is_unpaid' => false],
            ['name' => 'Compensatory Off', 'default_annual_allocation' => 0, 'is_unpaid' => false],
        ];

        foreach ($types as $t) {
            LeaveType::firstOrCreate(['name' => $t['name']], [
                'description' => $t['name'] . ' description',
                'default_annual_allocation' => $t['default_annual_allocation'],
                'is_active' => true,
                'is_unpaid' => $t['is_unpaid'],
            ]);
        }

        $this->casualLeave = LeaveType::where('name', 'Casual Leave')->first();
        $this->sickLeave = LeaveType::where('name', 'Sick Leave')->first();
        $this->unpaidLeave = LeaveType::where('name', 'Unpaid Leave')->first();
        $this->compOff = LeaveType::where('name', 'Compensatory Off')->first();
    }

    protected function createBalancesForEmployee(Employee $emp)
    {
        foreach (LeaveType::all() as $type) {
            LeaveBalance::firstOrCreate(
                ['employee_id' => $emp->id, 'leave_type_id' => $type->id, 'year' => 2026],
                [
                    'allocated_days' => $type->default_annual_allocation,
                    'used_days' => 0,
                    'remaining_days' => $type->default_annual_allocation,
                ]
            );
        }
    }

    /** @test 1 */
    public function test_1_all_8_leave_types_exist()
    {
        $this->assertEquals(8, LeaveType::count());
        $this->assertDatabaseHas('leave_types', ['name' => 'Casual Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Sick Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Earned Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Paid Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Unpaid Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Maternity Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Paternity Leave']);
        $this->assertDatabaseHas('leave_types', ['name' => 'Compensatory Off']);
    }

    /** @test 2 */
    public function test_2_leave_type_crud_authorization()
    {
        // Employee POST -> 403
        $responseEmp = $this->actingAs($this->employeeUser1)->postJson('/api/leave-types', [
            'name' => 'Hacker Leave',
            'default_annual_allocation' => 10,
        ]);
        $responseEmp->assertStatus(403);

        // HR Admin POST -> 201
        $responseHR = $this->actingAs($this->hrAdmin)->postJson('/api/leave-types', [
            'name' => 'Sabbatical Leave',
            'default_annual_allocation' => 30,
        ]);
        $responseHR->assertStatus(201);
        $this->assertDatabaseHas('leave_types', ['name' => 'Sabbatical Leave']);
    }

    /** @test 3 */
    public function test_3_employee_can_view_own_balance()
    {
        $response = $this->actingAs($this->employeeUser1)->getJson('/api/leave-balances');
        $response->assertStatus(200)->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertCount(8, $data);
    }

    /** @test 4 */
    public function test_4_employee_can_apply_for_leave()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-02',
            'reason' => 'Casual outing',
        ]);

        $response->assertStatus(201)->assertJsonFragment([
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee1->id,
            'status' => 'Pending',
        ]);
    }

    /** @test 5 */
    public function test_5_server_calculates_days_and_derives_manager_id_server_side()
    {
        // Provide spoofed/fake manager_id (999) from frontend - server must ignore and derive real manager
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-10',
            'to_date' => '2026-10-12', // 3 days inclusive
            'manager_id' => 999, // spoofed
            'reason' => 'Server derivation test',
        ]);

        $response->assertStatus(201)->assertJsonFragment([
            'number_of_days' => 3,
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee1->id,
            'number_of_days' => 3,
            'manager_id' => $this->managerEmployee->id, // Derived server-side from employee profile
        ]);
    }

    /** @test 6 */
    public function test_6_invalid_date_range_rejected()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-01', // Invalid: to_date before from_date
            'reason' => 'Backwards date test',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('to_date');
    }

    /** @test 7 */
    public function test_7_invalid_leave_type_rejected()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => 9999,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-02',
            'reason' => 'Invalid type test',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('leave_type_id');
    }

    /** @test 8 */
    public function test_8_employee_cannot_submit_leave_for_another_employee()
    {
        // Try to inject employee2's ID into request
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'employee_id' => $this->employee2->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-02',
            'reason' => 'Impersonation test',
        ]);

        $response->assertStatus(201);
        // Verify request created for employee1, NOT employee2
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => $this->employee1->id,
            'reason' => 'Impersonation test',
        ]);
        $this->assertDatabaseMissing('leave_requests', [
            'employee_id' => $this->employee2->id,
            'reason' => 'Impersonation test',
        ]);
    }

    /** @test 9 */
    public function test_9_insufficient_balance_rejected_for_paid_leave()
    {
        // Casual leave allocated is 12 days. Attempting 15 days should fail.
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-15', // 15 days
            'reason' => 'Exceeding balance',
        ]);

        $response->assertStatus(422);
    }

    /** @test 10 */
    public function test_10_unpaid_leave_allowed_regardless_of_balance()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->unpaidLeave->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-30', // 30 days unpaid
            'reason' => 'Extended sabbatical',
        ]);

        $response->assertStatus(201)->assertJsonFragment(['status' => 'Pending']);
    }

    /** @test 11 */
    public function test_11_manager_can_approve_direct_report_request()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
            'number_of_days' => 2,
            'reason' => 'Personal work',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req->id}/manager-approve");
        $response->assertStatus(200)->assertJsonFragment(['status' => 'Manager Approved']);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $req->id,
            'status' => 'Manager Approved',
        ]);

        // Manager approval must NOT deduct balance
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'used_days' => 0,
            'remaining_days' => 12,
        ]);
    }

    /** @test 12 */
    public function test_12_manager_cannot_approve_unrelated_employee_request()
    {
        $req2 = LeaveRequest::create([
            'employee_id' => $this->employee2->id, // Independent employee, not managed by managerUser
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
            'number_of_days' => 2,
            'reason' => 'Unrelated request',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req2->id}/manager-approve");
        $response->assertStatus(403);
    }

    /** @test 13 */
    public function test_13_employee_cannot_approve_own_request()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
            'number_of_days' => 2,
            'reason' => 'Self approval attempt',
            'status' => 'Pending',
        ]);

        $response = $this->actingAs($this->employeeUser1)->postJson("/api/leave-requests/{$req->id}/manager-approve");
        $response->assertStatus(403);
    }

    /** @test 14 */
    public function test_14_hr_cannot_approve_pending_request_directly()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
            'number_of_days' => 2,
            'reason' => 'Direct HR approval attempt',
            'status' => 'Pending',
        ]);

        // Attempting HR final approval on a Pending request (bypassing manager) must fail with 422
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve");
        $response->assertStatus(422);
    }

    /** @test 15 */
    public function test_15_final_hr_approval_deducts_balance_automatically()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-03', // 3 days
            'number_of_days' => 3,
            'reason' => 'Full approval workflow test',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        // Step 1: Manager Approve
        $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req->id}/manager-approve")->assertStatus(200);

        // Step 2: HR Final Approve
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve");
        $response->assertStatus(200)->assertJsonFragment(['status' => 'Approved']);

        // Verify balance updated: 12 allocated - 3 used = 9 remaining
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'year' => 2026,
            'used_days' => 3,
            'remaining_days' => 9,
        ]);
    }

    /** @test 16 */
    public function test_16_repeated_approval_cannot_double_deduct_balance()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-01',
            'to_date' => '2026-11-02',
            'number_of_days' => 2,
            'reason' => 'Double approval test',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req->id}/manager-approve")->assertStatus(200);
        $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve")->assertStatus(200);

        // Second attempt at final approval should be rejected
        $secondAttempt = $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve");
        $secondAttempt->assertStatus(422);

        // Verify balance was only deducted ONCE (2 days used)
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'year' => 2026,
            'used_days' => 2,
        ]);
    }

    /** @test 17 */
    public function test_17_rejected_or_cancelled_requests_do_not_block_new_leave_applications()
    {
        // Create and reject a request
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-12-01',
            'to_date' => '2026-12-05',
            'number_of_days' => 5,
            'reason' => 'First try',
            'status' => 'Rejected',
        ]);

        // Submit new request for the EXACT SAME date range
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->sickLeave->id,
            'from_date' => '2026-12-01',
            'to_date' => '2026-12-05',
            'reason' => 'Second try after rejection',
        ]);

        $response->assertStatus(201);
    }

    /** @test 18 */
    public function test_18_rejection_does_not_deduct_leave_balance()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-10',
            'to_date' => '2026-11-12',
            'number_of_days' => 3,
            'reason' => 'Rejection test',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $response = $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req->id}/reject", [
            'remarks' => 'Not feasible during project delivery',
        ]);

        $response->assertStatus(200)->assertJsonFragment(['status' => 'Rejected']);

        // Verify remaining balance remains 12
        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'used_days' => 0,
            'remaining_days' => 12,
        ]);
    }

    /** @test 19 */
    public function test_19_compensatory_off_balance_can_be_adjusted_by_authorized_hr()
    {
        // Adjust Compensatory Off balance for employee 1 from 0 to 5 days
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/employees/{$this->employee1->id}/leave-balances/adjust", [
            'leave_type_id' => $this->compOff->id,
            'allocated_days' => 5,
            'used_days' => 0,
            'year' => 2026,
        ]);

        $response->assertStatus(200)->assertJsonFragment([
            'allocated_days' => 5,
            'remaining_days' => 5,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->compOff->id,
            'allocated_days' => 5,
            'remaining_days' => 5,
        ]);
    }

    /** @test 20 */
    public function test_20_audit_records_are_generated_for_leave_actions()
    {
        $response = $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-11-20',
            'to_date' => '2026-11-21',
            'reason' => 'Audit logging test',
        ]);

        $reqId = $response->json('id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'leave_request.created',
            'entity_type' => LeaveRequest::class,
            'entity_id' => $reqId,
        ]);
    }

    /** @test 21 */
    public function test_21_rbac_403_enforcement_for_unauthorized_actions()
    {
        // Employee attempting to adjust balance -> 403
        $response = $this->actingAs($this->employeeUser1)->postJson("/api/employees/{$this->employee1->id}/leave-balances/adjust", [
            'leave_type_id' => $this->casualLeave->id,
            'allocated_days' => 100,
        ]);

        $response->assertStatus(403);
    }
}
