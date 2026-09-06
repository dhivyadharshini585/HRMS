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

class NotificationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $dept = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $desig = Designation::create(['title' => 'Software Engineer', 'code' => 'SE', 'department_id' => $dept->id]);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();

        $this->casualLeave = LeaveType::firstOrCreate(
            ['name' => 'Casual Leave'],
            [
                'description' => 'Casual leave',
                'default_annual_allocation' => 12,
                'is_active' => true,
                'is_unpaid' => false,
            ]
        );

        // Manager Employee
        $this->managerEmployee = Employee::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-NMGR-01',
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
            'employee_code' => 'EMP-NOTIF-01',
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
            'employee_code' => 'EMP-NOTIF-02',
            'first_name' => 'Bob',
            'last_name' => 'Independent',
            'email' => 'bob@hrms.local',
            'department_id' => $dept->id,
            'designation_id' => $desig->id,
            'employment_type' => 'Full-time',
            'employment_status' => 'Active',
            'date_of_joining' => '2025-01-01',
        ]);

        LeaveBalance::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'year' => 2026,
            'allocated_days' => 12,
            'used_days' => 0,
            'remaining_days' => 12,
        ]);
    }

    /** @test 1 */
    public function test_1_leave_submission_creates_correct_manager_notification()
    {
        $this->actingAs($this->employeeUser1)->postJson('/api/leave-requests', [
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-02',
            'reason' => 'Doctor appointment',
        ])->assertStatus(201);

        // Manager receives exactly 1 notification
        $this->assertEquals(1, $this->managerUser->notifications()->count());
        $notification = $this->managerUser->notifications()->first();
        $this->assertEquals('leave_submitted', $notification->data['type']);
        $this->assertEquals($this->employee1->id, $notification->data['employee_id']);

        // Employee receives NO submission notification (it was for manager)
        $this->assertEquals(0, $this->employeeUser1->notifications()->count());
    }

    /** @test 2 */
    public function test_2_manager_approval_creates_correct_hr_and_employee_notifications()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-06',
            'number_of_days' => 2,
            'reason' => 'Vacation',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req->id}/manager-approve", [
            'manager_remarks' => 'Looks good',
        ])->assertStatus(200);

        // Employee receives manager approval notification
        $this->assertEquals(1, $this->employeeUser1->notifications()->count());
        $empNotif = $this->employeeUser1->notifications()->first();
        $this->assertEquals('leave_manager_approved_employee', $empNotif->data['type']);

        // HR Admin receives manager approval notification
        $this->assertGreaterThanOrEqual(1, $this->hrAdmin->notifications()->count());
        $hrNotif = $this->hrAdmin->notifications()->first();
        $this->assertEquals('leave_manager_approved_hr', $hrNotif->data['type']);
    }

    /** @test 3 */
    public function test_3_hr_final_approval_creates_employee_notification_after_commit()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-06',
            'number_of_days' => 2,
            'reason' => 'Vacation',
            'status' => 'Manager Approved',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve", [
            'hr_remarks' => 'Approved by HR',
        ])->assertStatus(200);

        // Employee receives final approval notification
        $this->assertEquals(1, $this->employeeUser1->notifications()->count());
        $notif = $this->employeeUser1->notifications()->first();
        $this->assertEquals('leave_approved', $notif->data['type']);
        $this->assertEquals('Approved', $notif->data['status']);
    }

    /** @test 4 */
    public function test_4_rejection_creates_employee_notification()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-06',
            'number_of_days' => 2,
            'reason' => 'Vacation',
            'status' => 'Pending',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $this->actingAs($this->managerUser)->postJson("/api/leave-requests/{$req->id}/reject", [
            'rejection_reason' => 'Critical release week',
        ])->assertStatus(200);

        $this->assertEquals(1, $this->employeeUser1->notifications()->count());
        $notif = $this->employeeUser1->notifications()->first();
        $this->assertEquals('leave_rejected', $notif->data['type']);
        $this->assertEquals('Critical release week', $notif->data['rejection_reason']);
    }

    /** @test 5 */
    public function test_5_notification_privacy_and_mark_as_read_are_enforced()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-06',
            'number_of_days' => 2,
            'reason' => 'Vacation',
            'status' => 'Manager Approved',
            'manager_id' => $this->managerEmployee->id,
        ]);

        $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve")->assertStatus(200);

        $notifId = $this->employeeUser1->notifications()->first()->id;

        // 1. Employee 2 attempts to mark Employee 1's notification as read -> 404 (Privacy enforced)
        $this->actingAs($this->employeeUser2)->postJson("/api/notifications/{$notifId}/read")->assertStatus(404);

        // 2. Employee 2 index returns 0 notifications
        $emp2Res = $this->actingAs($this->employeeUser2)->getJson('/api/notifications');
        $this->assertEquals(0, $emp2Res->json('total'));

        // 3. Employee 1 views unread count -> 1
        $countRes = $this->actingAs($this->employeeUser1)->getJson('/api/notifications/unread-count');
        $this->assertEquals(1, $countRes->json('unread_count'));

        // 4. Employee 1 marks as read
        $readRes = $this->actingAs($this->employeeUser1)->postJson("/api/notifications/{$notifId}/read");
        $readRes->assertStatus(200)->assertJsonFragment(['unread_count' => 0]);

        $this->assertEquals(0, $this->employeeUser1->unreadNotifications()->count());
    }

    /** @test 6 */
    public function test_6_repeated_or_invalid_approval_does_not_create_duplicate_notifications()
    {
        $req = LeaveRequest::create([
            'employee_id' => $this->employee1->id,
            'leave_type_id' => $this->casualLeave->id,
            'from_date' => '2026-10-05',
            'to_date' => '2026-10-06',
            'number_of_days' => 2,
            'reason' => 'Vacation',
            'status' => 'Manager Approved',
            'manager_id' => $this->managerEmployee->id,
        ]);

        // First approval: creates 1 notification
        $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve")->assertStatus(200);
        $this->assertEquals(1, $this->employeeUser1->notifications()->count());

        // Second approval attempt: fails with 422
        $this->actingAs($this->hrAdmin)->postJson("/api/leave-requests/{$req->id}/hr-approve")->assertStatus(422);

        // Verify count remains strictly 1 (no duplicate notifications generated)
        $this->assertEquals(1, $this->employeeUser1->notifications()->count());
    }
}
