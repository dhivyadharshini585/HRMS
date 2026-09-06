<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\JobOpening;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterviewFeedbackTest extends TestCase
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
    protected Candidate $candidate1;

    protected Interview $completedInterviewManager;
    protected Interview $completedInterviewDirectReport;
    protected Interview $completedInterviewOther;
    protected Interview $scheduledInterview;
    protected Interview $cancelledInterview;

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

        // Other Interviewer Employee (standalone)
        $this->otherInterviewerEmployee = Employee::create([
            'employee_code' => 'EMP-OTH-003',
            'first_name' => 'Olivia',
            'last_name' => 'Other',
            'email' => 'olivia.other@hrms.local',
            'phone' => '+1 555-0103',
            'department_id' => $this->deptEngineering->id,
            'date_of_joining' => '2024-01-01',
            'employment_status' => 'Active',
            'employment_type' => 'Full-time',
        ]);

        // Job Opening
        $this->jobOpening1 = JobOpening::create([
            'job_code' => 'JOB-2026-0001',
            'title' => 'Senior Backend Engineer',
            'department_id' => $this->deptEngineering->id,
            'designation_id' => $this->desigDeveloper->id,
            'employment_type' => 'Full Time',
            'location' => 'Headquarters',
            'openings_count' => 2,
            'description' => 'Senior backend developer role',
            'status' => 'Open',
            'created_by' => $this->superAdmin->id,
        ]);

        // Candidate
        $this->candidate1 = Candidate::create([
            'candidate_code' => 'CAN-2026-0001',
            'job_opening_id' => $this->jobOpening1->id,
            'first_name' => 'Alice',
            'last_name' => 'Walker',
            'email' => 'alice.walker@example.com',
            'phone' => '+1 555-1111',
            'status' => 'Shortlisted',
            'created_by' => $this->superAdmin->id,
        ]);

        // Completed Interview with Manager as Interviewer
        $this->completedInterviewManager = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => Carbon::now()->subDays(2),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Completed',
            'created_by' => $this->superAdmin->id,
        ]);

        // Completed Interview with Direct Report as Interviewer
        $this->completedInterviewDirectReport = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->directReportEmployee->id,
            'interview_type' => 'HR',
            'interview_round' => 1,
            'scheduled_at' => Carbon::now()->subDays(1),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Completed',
            'created_by' => $this->superAdmin->id,
        ]);

        // Completed Interview with Other Interviewer
        $this->completedInterviewOther = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->otherInterviewerEmployee->id,
            'interview_type' => 'Managerial',
            'interview_round' => 2,
            'scheduled_at' => Carbon::now()->subDays(1),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Completed',
            'created_by' => $this->superAdmin->id,
        ]);

        // Scheduled (not completed) Interview
        $this->scheduledInterview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Final',
            'interview_round' => 3,
            'scheduled_at' => Carbon::now()->addDays(2),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Scheduled',
            'created_by' => $this->superAdmin->id,
        ]);

        // Cancelled Interview
        $this->cancelledInterview = Interview::create([
            'candidate_id' => $this->candidate1->id,
            'job_opening_id' => $this->jobOpening1->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => Carbon::now()->subDays(5),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Cancelled',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    /**
     * 1. Test HR Admin can view feedback
     */
    public function test_hr_admin_can_view_feedback(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 4,
            'technical_rating' => 4,
            'communication_rating' => 5,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $feedback->id)
            ->assertJsonPath('data.overall_rating', 4)
            ->assertJsonPath('data.recommendation', 'Hire')
            ->assertJsonPath('interview.candidate_name', 'Alice Walker');
    }

    /**
     * 2. Test HR Admin can create feedback
     */
    public function test_hr_admin_can_create_feedback(): void
    {
        $payload = [
            'overall_rating' => 5,
            'technical_rating' => 5,
            'communication_rating' => 4,
            'problem_solving_rating' => 5,
            'cultural_fit_rating' => 5,
            'strengths' => 'Exceptional system architecture understanding.',
            'weaknesses' => 'None observed.',
            'comments' => 'Strong candidate.',
            'recommendation' => 'Strong Hire',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.overall_rating', 5)
            ->assertJsonPath('data.recommendation', 'Strong Hire')
            ->assertJsonPath('data.interviewer_employee_id', $this->managerEmployee->id);

        $this->assertDatabaseHas('interview_feedback', [
            'interview_id' => $this->completedInterviewManager->id,
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
        ]);
    }

    /**
     * 3. Test HR Admin can update feedback
     */
    public function test_hr_admin_can_update_feedback(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 3,
            'recommendation' => 'Hold',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'comments' => 'Re-evaluated test task and improved rating.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.overall_rating', 4)
            ->assertJsonPath('data.recommendation', 'Hire');

        $this->assertDatabaseHas('interview_feedback', [
            'id' => $feedback->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);
    }

    /**
     * 4. Test HR Admin can delete feedback
     */
    public function test_hr_admin_can_delete_feedback(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Interview feedback deleted successfully.');

        $this->assertSoftDeleted('interview_feedback', [
            'id' => $feedback->id,
        ]);
    }

    /**
     * 5. Test HR Executive can view, create, and update feedback without being assigned interviewer
     */
    public function test_hr_executive_can_view_create_and_update_feedback(): void
    {
        // 1. Create feedback by HR Exec on manager's interview (HR Exec is NOT the interviewer)
        $responseCreate = $this->actingAs($this->hrExecutive)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'comments' => 'Submitted by HR Executive on behalf of interviewer.',
        ]);

        $responseCreate->assertStatus(201)
            ->assertJsonPath('data.recommendation', 'Hire');

        // 2. View feedback
        $responseView = $this->actingAs($this->hrExecutive)->getJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");
        $responseView->assertStatus(200)->assertJsonPath('data.overall_rating', 4);

        // 3. Update feedback
        $responseUpdate = $this->actingAs($this->hrExecutive)->putJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
        ]);
        $responseUpdate->assertStatus(200)->assertJsonPath('data.recommendation', 'Strong Hire');
    }

    /**
     * 6. Test HR Executive cannot delete feedback
     */
    public function test_hr_executive_cannot_delete_feedback(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrExecutive)->deleteJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");

        $response->assertStatus(403);
        $this->assertDatabaseHas('interview_feedback', ['id' => $feedback->id, 'deleted_at' => null]);
    }

    /**
     * 7. Test assigned interviewer (Manager) can submit feedback
     */
    public function test_assigned_interviewer_manager_can_submit_feedback(): void
    {
        $response = $this->actingAs($this->managerUser)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'technical_rating' => 4,
            'recommendation' => 'Hire',
            'comments' => 'Good technical depth.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.overall_rating', 4)
            ->assertJsonPath('data.interviewer_employee_id', $this->managerEmployee->id);
    }

    /**
     * 8. Test unassigned interviewer (Manager) cannot submit feedback
     */
    public function test_unassigned_interviewer_manager_cannot_submit_feedback(): void
    {
        // Manager attempts to submit feedback for completedInterviewOther (where Olivia is interviewer)
        $response = $this->actingAs($this->managerUser)->postJson("/api/interviews/{$this->completedInterviewOther->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 9. Test user cannot impersonate another interviewer (server derives interviewer from interview)
     */
    public function test_user_cannot_impersonate_another_interviewer(): void
    {
        // User sends another interviewer_employee_id in payload
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'interviewer_employee_id' => $this->otherInterviewerEmployee->id, // Fake/tampered ID
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);

        $response->assertStatus(201);
        // Verify server ignored the tampered interviewer_employee_id and derived it from interview
        $this->assertEquals($this->managerEmployee->id, $response->json('data.interviewer_employee_id'));
    }

    /**
     * 10. Test duplicate feedback is rejected
     */
    public function test_duplicate_feedback_is_rejected(): void
    {
        InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('interview_id');
    }

    /**
     * 11. Test invalid ratings are rejected
     */
    public function test_invalid_ratings_rejected(): void
    {
        // overall_rating = 0
        $response1 = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 0,
            'recommendation' => 'Hire',
        ]);
        $response1->assertStatus(422)->assertJsonValidationErrors('overall_rating');

        // overall_rating = 6
        $response2 = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 6,
            'recommendation' => 'Hire',
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors('overall_rating');

        // technical_rating = 10
        $response3 = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'technical_rating' => 10,
            'recommendation' => 'Hire',
        ]);
        $response3->assertStatus(422)->assertJsonValidationErrors('technical_rating');
    }

    /**
     * 12. Test invalid recommendation is rejected
     */
    public function test_invalid_recommendation_rejected(): void
    {
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Maybe Hire', // Invalid enum
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('recommendation');
    }

    /**
     * 13. Test missing or invalid interview returns 404
     */
    public function test_missing_or_invalid_interview_returns_404(): void
    {
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/interviews/999999/feedback');
        $response->assertStatus(404);

        $responseCreate = $this->actingAs($this->hrAdmin)->postJson('/api/interviews/999999/feedback', [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);
        $responseCreate->assertStatus(404);
    }

    /**
     * 14. Test unauthorized Employee receives 403
     */
    public function test_unauthorized_employee_receives_403(): void
    {
        $responseGet = $this->actingAs($this->employeeUser)->getJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");
        $responseGet->assertStatus(403);

        $responsePost = $this->actingAs($this->employeeUser)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);
        $responsePost->assertStatus(403);
    }

    /**
     * 15. Test Finance/Payroll Admin receives 403
     */
    public function test_finance_admin_receives_403(): void
    {
        $responseGet = $this->actingAs($this->payrollUser)->getJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");
        $responseGet->assertStatus(403);

        $responsePost = $this->actingAs($this->payrollUser)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);
        $responsePost->assertStatus(403);
    }

    /**
     * 16. Test Manager can access authorized interview feedback (own or direct report's)
     */
    public function test_manager_can_access_authorized_interview_feedback(): void
    {
        // 1. Manager views feedback for own interview
        $feedbackOwn = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
            'submitted_at' => now(),
        ]);

        $resOwn = $this->actingAs($this->managerUser)->getJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");
        $resOwn->assertStatus(200)->assertJsonPath('data.id', $feedbackOwn->id);

        // 2. Manager views feedback for direct report's interview
        $feedbackDirect = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewDirectReport->id,
            'interviewer_employee_id' => $this->directReportEmployee->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $resDirect = $this->actingAs($this->managerUser)->getJson("/api/interviews/{$this->completedInterviewDirectReport->id}/feedback");
        $resDirect->assertStatus(200)->assertJsonPath('data.id', $feedbackDirect->id);
    }

    /**
     * 17. Test Manager cannot access unrelated feedback
     */
    public function test_manager_cannot_access_unrelated_feedback(): void
    {
        $feedbackOther = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewOther->id,
            'interviewer_employee_id' => $this->otherInterviewerEmployee->id,
            'overall_rating' => 2,
            'recommendation' => 'No Hire',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->managerUser)->getJson("/api/interviews/{$this->completedInterviewOther->id}/feedback");
        $response->assertStatus(403);
    }

    /**
     * 18. Test Manager can submit feedback ONLY when assigned interviewer
     */
    public function test_manager_cannot_submit_feedback_for_direct_reports_interview(): void
    {
        // Manager can VIEW direct report's interview feedback, but CANNOT submit feedback on their behalf
        $response = $this->actingAs($this->managerUser)->postJson("/api/interviews/{$this->completedInterviewDirectReport->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 19. Test feedback for incomplete/cancelled interview is rejected
     */
    public function test_feedback_for_incomplete_or_cancelled_interview_is_rejected(): void
    {
        // Scheduled interview
        $resScheduled = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->scheduledInterview->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);
        $resScheduled->assertStatus(422)->assertJsonValidationErrors('interview_id');

        // Cancelled interview
        $resCancelled = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->cancelledInterview->id}/feedback", [
            'overall_rating' => 4,
            'recommendation' => 'Hire',
        ]);
        $resCancelled->assertStatus(422)->assertJsonValidationErrors('interview_id');
    }

    /**
     * 20. Test update feedback works
     */
    public function test_update_feedback_works(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 3,
            'technical_rating' => 3,
            'recommendation' => 'Hold',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->managerUser)->putJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 4,
            'technical_rating' => 5,
            'recommendation' => 'Hire',
            'comments' => 'Candidate provided excellent code sample after interview.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.overall_rating', 4)
            ->assertJsonPath('data.technical_rating', 5)
            ->assertJsonPath('data.recommendation', 'Hire');
    }

    /**
     * 21. Test delete feedback works where authorized (Super Admin and HR Admin)
     */
    public function test_delete_feedback_works_where_authorized(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->superAdmin)->deleteJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");
        $response->assertStatus(200);

        $this->assertSoftDeleted('interview_feedback', ['id' => $feedback->id]);
    }

    /**
     * 22. Test audit logs created, updated, and deleted correctly
     */
    public function test_audit_logs_recorded_for_feedback_actions(): void
    {
        // 1. Create
        $resCreate = $this->actingAs($this->hrAdmin)->postJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
        ]);
        $feedbackId = $resCreate->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'interview_feedback.created',
            'entity_id' => $feedbackId,
            'entity_type' => InterviewFeedback::class,
        ]);

        // 2. Update
        $this->actingAs($this->hrAdmin)->putJson("/api/interviews/{$this->completedInterviewManager->id}/feedback", [
            'comments' => 'Updated by HR Admin.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'interview_feedback.updated',
            'entity_id' => $feedbackId,
            'entity_type' => InterviewFeedback::class,
        ]);

        // 3. Delete
        $this->actingAs($this->hrAdmin)->deleteJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'interview_feedback.deleted',
            'entity_id' => $feedbackId,
            'entity_type' => InterviewFeedback::class,
        ]);
    }

    /**
     * 23. Test soft-deleted feedback is excluded from active query and can be viewed with trashed
     */
    public function test_soft_deleted_feedback_behavior(): void
    {
        $feedback = InterviewFeedback::create([
            'interview_id' => $this->completedInterviewManager->id,
            'interviewer_employee_id' => $this->managerEmployee->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'submitted_at' => now(),
        ]);

        $feedback->delete();

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/interviews/{$this->completedInterviewManager->id}/feedback");
        $response->assertStatus(404);

        $this->assertNotNull(InterviewFeedback::withTrashed()->find($feedback->id)->deleted_at);
    }

    /**
     * 24. Test Super Admin has full access to feedback
     */
    public function test_super_admin_has_full_feedback_access(): void
    {
        $resCreate = $this->actingAs($this->superAdmin)->postJson("/api/interviews/{$this->completedInterviewOther->id}/feedback", [
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
        ]);
        $resCreate->assertStatus(201);

        $resGet = $this->actingAs($this->superAdmin)->getJson("/api/interviews/{$this->completedInterviewOther->id}/feedback");
        $resGet->assertStatus(200);

        $resUpdate = $this->actingAs($this->superAdmin)->putJson("/api/interviews/{$this->completedInterviewOther->id}/feedback", [
            'overall_rating' => 4,
        ]);
        $resUpdate->assertStatus(200);

        $resDelete = $this->actingAs($this->superAdmin)->deleteJson("/api/interviews/{$this->completedInterviewOther->id}/feedback");
        $resDelete->assertStatus(200);
    }
}
