<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $hrExecutive;
    protected User $managerUser;
    protected User $employeeUser;
    protected User $payrollUser;

    protected Employee $managerEmployee;
    protected Employee $directReportEmployee;
    protected Employee $otherInterviewerEmployee;

    protected Department $deptEngineering;
    protected Designation $desigDeveloper;
    protected JobOpening $jobOpening1;
    protected JobOpening $jobOpening2;
    protected Candidate $candidate1;
    protected Candidate $candidate2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->hrExecutive = User::where('email', 'hrexecutive@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();
        $this->payrollUser = User::where('email', 'payroll@hrms.local')->first();

        $this->deptEngineering = Department::create([
            'name' => 'Engineering',
            'description' => 'Software & Hardware',
        ]);

        $this->desigDeveloper = Designation::create([
            'title' => 'Senior Developer',
            'description' => 'Core Systems',
        ]);

        // Manager Employee
        $this->managerEmployee = Employee::create([
            'user_id' => $this->managerUser->id,
            'employee_code' => 'EMP-MGR-001',
            'first_name' => 'Michael',
            'last_name' => 'Manager',
            'email' => 'manager@hrms.local',
            'phone' => '+1 555-0101',
            'department_id' => $this->deptEngineering->id,
            'date_of_joining' => '2024-01-01',
            'employment_status' => 'Active',
            'employment_type' => 'Full-time',
        ]);

        // Direct Report Employee (reports to managerEmployee)
        $this->directReportEmployee = Employee::create([
            'employee_code' => 'EMP-DIR-002',
            'first_name' => 'Dan',
            'last_name' => 'Report',
            'email' => 'dan.report@hrms.local',
            'phone' => '+1 555-0102',
            'department_id' => $this->deptEngineering->id,
            'manager_id' => $this->managerEmployee->id,
            'date_of_joining' => '2024-01-01',
            'employment_status' => 'Active',
            'employment_type' => 'Full-time',
        ]);

        // Other Interviewer Employee (different manager/standalone)
        $this->otherInterviewerEmployee = Employee::create([
            'employee_code' => 'EMP-OTH-003',
            'first_name' => 'Oscar',
            'last_name' => 'Other',
            'email' => 'oscar.other@hrms.local',
            'phone' => '+1 555-0103',
            'department_id' => $this->deptEngineering->id,
            'date_of_joining' => '2024-01-01',
            'employment_status' => 'Active',
            'employment_type' => 'Full-time',
        ]);

        // Job Openings
        $this->jobOpening1 = JobOpening::create([
            'title' => 'Backend Architect',
            'job_code' => 'JOB-ARC-001',
            'department_id' => $this->deptEngineering->id,
            'designation_id' => $this->desigDeveloper->id,
            'employment_type' => 'Full Time',
            'location' => 'Austin, TX',
            'openings_count' => 1,
            'description' => 'Backend Architect opening',
            'status' => 'Open',
        ]);

        $this->jobOpening2 = JobOpening::create([
            'title' => 'Frontend Lead',
            'job_code' => 'JOB-FE-002',
            'department_id' => $this->deptEngineering->id,
            'designation_id' => $this->desigDeveloper->id,
            'employment_type' => 'Full Time',
            'location' => 'Austin, TX',
            'openings_count' => 1,
            'description' => 'Frontend Lead opening',
            'status' => 'Open',
        ]);

        // Candidates
        $this->candidate1 = Candidate::create([
            'candidate_code' => 'CAN-00001',
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'email' => 'alice.wonder@example.com',
            'phone' => '+1 555-1111',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Screening',
        ]);

        $this->candidate2 = Candidate::create([
            'candidate_code' => 'CAN-00002',
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'email' => 'bob.builder@example.com',
            'phone' => '+1 555-2222',
            'job_opening_id' => $this->jobOpening2->id,
            'status' => 'Screening',
        ]);
    }

    /**
     * 1. Authorized HR Admin can list interviews.
     */
    public function test_1_authorized_hr_can_list_interviews(): void
    {
        Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => Carbon::now()->addDays(2)->setHour(10)->setMinute(0),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/interviews');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * 2. Authorized HR Admin can schedule an interview.
     */
    public function test_2_authorized_hr_can_schedule_interview(): void
    {
        $payload = [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => Carbon::now()->addDays(3)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'location_or_link' => 'https://meet.google.com/abc-defg-hij',
            'remarks' => 'Focus on distributed caching and concurrency.',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Interview scheduled successfully')
            ->assertJsonPath('data.candidate_id', $this->candidate1->id)
            ->assertJsonPath('data.status', 'Scheduled');

        $this->assertDatabaseHas('interviews', [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'status' => 'Scheduled',
        ]);
    }

    /**
     * 3. Candidate and Job Opening mismatch is rejected with HTTP 422.
     */
    public function test_3_candidate_and_job_opening_mismatch_is_rejected(): void
    {
        // candidate1 belongs to jobOpening1, but payload specifies jobOpening2
        $payload = [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening2->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_opening_id']);
    }

    /**
     * 4. Missing required job_opening_id is rejected with HTTP 422.
     */
    public function test_4_missing_required_job_opening_id_is_rejected(): void
    {
        $payload = [
            'candidate_id' => $this->candidate1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_opening_id']);
    }

    /**
     * 5. Missing required candidate_id is rejected with HTTP 422.
     */
    public function test_5_missing_required_candidate_id_is_rejected(): void
    {
        $payload = [
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['candidate_id']);
    }

    /**
     * 6. Inactive interviewer employee is rejected with HTTP 422.
     */
    public function test_6_inactive_interviewer_is_rejected(): void
    {
        $inactiveEmployee = Employee::create([
            'employee_code' => 'EMP-INA-999',
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'email' => 'inactive@hrms.local',
            'phone' => '+1 555-9999',
            'department_id' => $this->deptEngineering->id,
            'date_of_joining' => '2024-01-01',
            'employment_status' => 'Terminated',
            'employment_type' => 'Full-time',
        ]);

        $payload = [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $inactiveEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Phone',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interviewer_employee_id']);
    }

    /**
     * 7. Interviewer scheduling conflict is rejected with HTTP 422.
     */
    public function test_7_interviewer_scheduling_conflict_is_rejected(): void
    {
        $baseTime = Carbon::now()->addDays(2)->setHour(14)->setMinute(0);

        // Existing interview: 14:00 to 15:00 (60 mins)
        Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => $baseTime,
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        // Overlapping attempt: 14:30 to 15:15
        $payload = [
            'candidate_id' => $this->candidate2->id,
            'job_opening_id' => $this->jobOpening2->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Managerial',
            'scheduled_at' => $baseTime->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
    }

    /**
     * 8. Non-overlapping interview for same interviewer succeeds (HTTP 201).
     */
    public function test_8_non_overlapping_interview_for_same_interviewer_succeeds(): void
    {
        $baseTime = Carbon::now()->addDays(2)->setHour(14)->setMinute(0);

        // Existing interview: 14:00 to 15:00
        Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => $baseTime,
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        // Safe time: 15:30 to 16:15
        $payload = [
            'candidate_id' => $this->candidate2->id,
            'job_opening_id' => $this->jobOpening2->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Managerial',
            'scheduled_at' => $baseTime->copy()->addMinutes(90)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Interview scheduled successfully');
    }

    /**
     * 9. Authorized HR can view interview details.
     */
    public function test_9_authorized_hr_can_view_interview_details(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(1),
            'duration_minutes' => 30,
            'mode' => 'Phone',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/interviews/{$interview->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $interview->id)
            ->assertJsonPath('data.candidate.candidate_code', $this->candidate1->candidate_code);
    }

    /**
     * 10. Authorized HR can update interview details.
     */
    public function test_10_authorized_hr_can_update_interview(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(1),
            'duration_minutes' => 30,
            'mode' => 'Phone',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/interviews/{$interview->id}", [
            'mode' => 'Online',
            'location_or_link' => 'https://zoom.us/j/123456789',
            'remarks' => 'Updated link',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Interview updated successfully')
            ->assertJsonPath('data.mode', 'Online');

        $this->assertDatabaseHas('interviews', [
            'id' => $interview->id,
            'mode' => 'Online',
            'location_or_link' => 'https://zoom.us/j/123456789',
        ]);
    }

    /**
     * 11. Rescheduling interview logs audit action interview.rescheduled.
     */
    public function test_11_rescheduling_interview_logs_audit_action(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(1)->setHour(10)->setMinute(0),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        $newTime = Carbon::now()->addDays(3)->setHour(15)->setMinute(0);

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/interviews/{$interview->id}", [
            'scheduled_at' => $newTime->format('Y-m-d H:i:s'),
            'status' => 'Rescheduled',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Interview::class,
            'entity_id' => $interview->id,
            'action' => 'interview.rescheduled',
        ]);
    }

    /**
     * 12. Cancelling interview logs audit action interview.cancelled.
     */
    public function test_12_cancelling_interview_logs_audit_action(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Final',
            'scheduled_at' => Carbon::now()->addDays(2),
            'duration_minutes' => 45,
            'mode' => 'In-person',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/interviews/{$interview->id}", [
            'status' => 'Cancelled',
            'remarks' => 'Candidate accepted another offer.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Cancelled');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Interview::class,
            'entity_id' => $interview->id,
            'action' => 'interview.cancelled',
        ]);
    }

    /**
     * 13. Authorized HR Admin can delete interview (Soft Delete).
     */
    public function test_13_authorized_hr_admin_can_delete_interview(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(2),
            'duration_minutes' => 30,
            'mode' => 'Phone',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/interviews/{$interview->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Interview deleted successfully');

        $this->assertSoftDeleted('interviews', [
            'id' => $interview->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Interview::class,
            'entity_id' => $interview->id,
            'action' => 'interview.deleted',
        ]);
    }

    /**
     * 14. HR Executive can list, create, and update interviews.
     */
    public function test_14_hr_executive_can_list_create_and_update_interviews(): void
    {
        // 1. Create
        $response = $this->actingAs($this->hrExecutive)->postJson('/api/interviews', [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ]);
        $response->assertStatus(201);
        $id = $response->json('data.id');

        // 2. List
        $this->actingAs($this->hrExecutive)->getJson('/api/interviews')->assertStatus(200);

        // 3. Update
        $this->actingAs($this->hrExecutive)->putJson("/api/interviews/{$id}", [
            'remarks' => 'Executive notes',
        ])->assertStatus(200);
    }

    /**
     * 15. HR Executive is blocked from deleting interview (strictly HTTP 403).
     */
    public function test_15_hr_executive_is_blocked_from_deleting_interview(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(2),
            'duration_minutes' => 30,
            'mode' => 'Phone',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->hrExecutive)->deleteJson("/api/interviews/{$interview->id}");

        $response->assertStatus(403);
    }

    /**
     * 16. Manager can view interviews where they are the interviewer.
     */
    public function test_16_manager_can_view_interviews_where_they_are_interviewer(): void
    {
        // Manager's own interview
        $interview1 = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Managerial',
            'scheduled_at' => Carbon::now()->addDays(1),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->managerUser)->getJson('/api/interviews');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($interview1->id));

        $showResponse = $this->actingAs($this->managerUser)->getJson("/api/interviews/{$interview1->id}");
        $showResponse->assertStatus(200);
    }

    /**
     * 17. Manager can view interviews where their direct report is the interviewer.
     */
    public function test_17_manager_can_view_interviews_of_their_direct_reports(): void
    {
        // Direct report's interview
        $interview2 = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->directReportEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(2),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        $response = $this->actingAs($this->managerUser)->getJson('/api/interviews');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($interview2->id));

        $showResponse = $this->actingAs($this->managerUser)->getJson("/api/interviews/{$interview2->id}");
        $showResponse->assertStatus(200);
    }

    /**
     * 18. Manager CANNOT view interviews for unrelated interviewers (excluded from list and 403 on show).
     */
    public function test_18_manager_cannot_view_interviews_for_other_interviewers(): void
    {
        // Unrelated interviewer's interview (not manager, not direct report)
        $interviewOther = Interview::create([
            'candidate_id' => $this->candidate2->id,
            'job_opening_id' => $this->jobOpening2->id,
            'interviewer_employee_id' => $this->otherInterviewerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(3),
            'duration_minutes' => 30,
            'mode' => 'Phone',
            'status' => 'Scheduled',
        ]);

        // List check: should not contain interviewOther
        $response = $this->actingAs($this->managerUser)->getJson('/api/interviews');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($interviewOther->id));

        // Direct show check: strictly HTTP 403
        $showResponse = $this->actingAs($this->managerUser)->getJson("/api/interviews/{$interviewOther->id}");
        $showResponse->assertStatus(403);
    }

    /**
     * 19. Manager cannot create, update, or delete interviews (HTTP 403).
     */
    public function test_19_manager_cannot_create_update_or_delete_interviews(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Managerial',
            'scheduled_at' => Carbon::now()->addDays(1),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        // Create -> 403
        $this->actingAs($this->managerUser)->postJson('/api/interviews', [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Managerial',
            'scheduled_at' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ])->assertStatus(403);

        // Update -> 403
        $this->actingAs($this->managerUser)->putJson("/api/interviews/{$interview->id}", [
            'mode' => 'Phone',
        ])->assertStatus(403);

        // Delete -> 403
        $this->actingAs($this->managerUser)->deleteJson("/api/interviews/{$interview->id}")->assertStatus(403);
    }

    /**
     * 20. Employee is blocked from all interview endpoints (HTTP 403).
     */
    public function test_20_employee_is_blocked_from_all_interview_endpoints(): void
    {
        $interview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(1),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        $this->actingAs($this->employeeUser)->getJson('/api/interviews')->assertStatus(403);
        $this->actingAs($this->employeeUser)->getJson("/api/interviews/{$interview->id}")->assertStatus(403);
        $this->actingAs($this->employeeUser)->postJson('/api/interviews', [])->assertStatus(403);
        $this->actingAs($this->employeeUser)->putJson("/api/interviews/{$interview->id}", [])->assertStatus(403);
        $this->actingAs($this->employeeUser)->deleteJson("/api/interviews/{$interview->id}")->assertStatus(403);
    }

    /**
     * 21. Finance/Payroll Admin is blocked from all interview endpoints (HTTP 403).
     */
    public function test_21_finance_payroll_is_blocked_from_all_interview_endpoints(): void
    {
        $this->actingAs($this->payrollUser)->getJson('/api/interviews')->assertStatus(403);
        $this->actingAs($this->payrollUser)->postJson('/api/interviews', [])->assertStatus(403);
    }

    /**
     * 22. Filtering by candidate, job opening, interviewer, status, and interview type.
     */
    public function test_22_filtering_interviews(): void
    {
        $inv1 = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(1),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Scheduled',
        ]);

        $inv2 = Interview::create([
            'candidate_id' => $this->candidate2->id,
            'job_opening_id' => $this->jobOpening2->id,
            'interviewer_employee_id' => $this->otherInterviewerEmployee->id,
            'interview_type' => 'HR',
            'scheduled_at' => Carbon::now()->addDays(2),
            'duration_minutes' => 30,
            'mode' => 'Phone',
            'status' => 'Completed',
        ]);

        // Filter candidate 1
        $res = $this->actingAs($this->hrAdmin)->getJson("/api/interviews?candidate_id={$this->candidate1->id}");
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($inv1->id, $res->json('data.0.id'));

        // Filter status Completed
        $resStatus = $this->actingAs($this->hrAdmin)->getJson('/api/interviews?status=Completed');
        $resStatus->assertStatus(200);
        $this->assertCount(1, $resStatus->json('data'));
        $this->assertEquals($inv2->id, $resStatus->json('data.0.id'));
    }

    /**
     * 23. Pagination works correctly.
     */
    public function test_23_pagination_works_correctly(): void
    {
        for ($i = 0; $i < 15; $i++) {
            Interview::create([
                'candidate_id' => $this->candidate1->id,
                'job_opening_id' => $this->jobOpening1->id,
                'interviewer_employee_id' => $this->managerEmployee->id,
                'interview_type' => 'Technical',
                'scheduled_at' => Carbon::now()->addDays($i + 1)->setHour(10)->setMinute(0),
                'duration_minutes' => 30,
                'mode' => 'Online',
                'status' => 'Scheduled',
            ]);
        }

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/interviews?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 15);
        $this->assertCount(10, $response->json('data'));
    }

    /**
     * 24. AuditLog records interview lifecycle events.
     */
    public function test_24_audit_logs_record_interview_lifecycle(): void
    {
        // 1. Create
        $res = $this->actingAs($this->hrAdmin)->postJson('/api/interviews', [
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'scheduled_at' => Carbon::now()->addDays(2)->format('Y-m-d H:i:s'),
            'duration_minutes' => 45,
            'mode' => 'Online',
        ]);
        $res->assertStatus(201);
        $interviewId = $res->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Interview::class,
            'entity_id' => $interviewId,
            'action' => 'interview.created',
        ]);

        // 2. Update
        $this->actingAs($this->hrAdmin)->putJson("/api/interviews/{$interviewId}", [
            'remarks' => 'Updated remarks',
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Interview::class,
            'entity_id' => $interviewId,
            'action' => 'interview.updated',
        ]);

        // 3. Delete
        $this->actingAs($this->hrAdmin)->deleteJson("/api/interviews/{$interviewId}")->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Interview::class,
            'entity_id' => $interviewId,
            'action' => 'interview.deleted',
        ]);
    }
}
