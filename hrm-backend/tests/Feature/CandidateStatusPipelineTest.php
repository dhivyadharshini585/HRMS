<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\CandidateStatusHistory;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Models\JobOpening;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CandidateStatusPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $hrExecutive;
    protected User $managerUser;
    protected User $employeeUser;
    protected User $payrollUser;

    protected Department $dept;
    protected Designation $desig;
    protected Employee $interviewerEmp;
    protected JobOpening $jobOpening;

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

        $this->dept = Department::create([
            'name' => 'Engineering',
            'description' => 'Software engineering',
        ]);

        $this->desig = Designation::create([
            'title' => 'Backend Engineer',
            'description' => 'PHP/Laravel',
        ]);

        $this->interviewerEmp = Employee::create([
            'employee_code' => 'EMP-TEST-001',
            'first_name' => 'Jane',
            'last_name' => 'Interviewer',
            'email' => 'jane.interviewer@hrms.local',
            'phone' => '+1 555-0101',
            'department_id' => $this->dept->id,
            'employment_status' => 'Active',
            'employment_type' => 'Full-time',
            'date_of_joining' => '2024-01-01',
        ]);

        $this->jobOpening = JobOpening::create([
            'job_code' => 'JOB-PIPE-001',
            'title' => 'Senior Backend Engineer',
            'department_id' => $this->dept->id,
            'designation_id' => $this->desig->id,
            'openings_count' => 2,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'description' => 'Job opening for testing status pipeline',
            'status' => 'Open',
            'created_by' => $this->hrAdmin->id,
        ]);
    }

    protected function createCandidate(string $status = 'New'): Candidate
    {
        static $counter = 1;
        $code = sprintf('CAN-PIPE-%04d', $counter++);

        return Candidate::create([
            'candidate_code' => $code,
            'first_name' => 'Candidate',
            'last_name' => 'User ' . $counter,
            'email' => "candidate{$counter}@test.local",
            'phone' => '+1 555-02' . str_pad((string)$counter, 2, '0', STR_PAD_LEFT),
            'job_opening_id' => $this->jobOpening->id,
            'status' => $status,
            'created_by' => $this->hrAdmin->id,
        ]);
    }

    /**
     * 1. New candidate gets initial New history record automatically and atomically
     */
    public function test_1_new_candidate_gets_initial_new_history(): void
    {
        $candidate = $this->createCandidate('New');

        $this->assertDatabaseHas('candidate_status_histories', [
            'candidate_id' => $candidate->id,
            'from_status' => null,
            'to_status' => 'New',
        ]);

        $this->assertCount(1, $candidate->statusHistories);
        $initialHistory = $candidate->statusHistories->first();
        $this->assertNull($initialHistory->from_status);
        $this->assertEquals('New', $initialHistory->to_status);
    }

    /**
     * 2. New -> Screening succeeds
     */
    public function test_2_new_to_screening_succeeds(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
            'remarks' => 'Passed initial profile check',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Screening');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'Screening',
        ]);

        $this->assertDatabaseHas('candidate_status_histories', [
            'candidate_id' => $candidate->id,
            'from_status' => 'New',
            'to_status' => 'Screening',
            'remarks' => 'Passed initial profile check',
        ]);
    }

    /**
     * 3. Screening -> Shortlisted succeeds
     */
    public function test_3_screening_to_shortlisted_succeeds(): void
    {
        $candidate = $this->createCandidate('Screening');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Shortlisted',
            'remarks' => 'Passed resume screening',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Shortlisted');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'Shortlisted',
        ]);
    }

    /**
     * 4. Screening -> Rejected succeeds with remarks
     */
    public function test_4_screening_to_rejected_succeeds_with_remarks(): void
    {
        $candidate = $this->createCandidate('Screening');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Rejected',
            'remarks' => 'Skills do not align with job requirements',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Rejected');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'Rejected',
        ]);

        $this->assertDatabaseHas('candidate_status_histories', [
            'candidate_id' => $candidate->id,
            'from_status' => 'Screening',
            'to_status' => 'Rejected',
            'remarks' => 'Skills do not align with job requirements',
        ]);
    }

    /**
     * 5. Shortlisted -> Rejected succeeds with remarks
     */
    public function test_5_shortlisted_to_rejected_succeeds_with_remarks(): void
    {
        $candidate = $this->createCandidate('Shortlisted');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Rejected',
            'remarks' => 'Candidate declined or did not meet round standards',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Rejected');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'Rejected',
        ]);
    }

    /**
     * 6. Shortlisted -> Hired succeeds when prerequisites are satisfied
     */
    public function test_6_shortlisted_to_hired_succeeds_when_prerequisites_are_satisfied(): void
    {
        $candidate = $this->createCandidate('Shortlisted');

        // Create a completed interview
        $interview = Interview::create([
            'candidate_id' => $candidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Completed',
            'created_by' => $this->hrAdmin->id,
        ]);

        // Submit feedback for the completed interview
        InterviewFeedback::create([
            'interview_id' => $interview->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
            'comments' => 'Excellent problem solving skills',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
            'remarks' => 'Offered and accepted position',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Hired');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'Hired',
        ]);
    }

    /**
     * 7. Invalid transition is rejected
     */
    public function test_7_invalid_transition_is_rejected(): void
    {
        $candidate = $this->createCandidate('New');

        // New -> Shortlisted is not directly allowed
        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Shortlisted',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * 8. New -> Hired rejected
     */
    public function test_8_new_to_hired_rejected(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * 9. Screening -> Hired rejected
     */
    public function test_9_screening_to_hired_rejected(): void
    {
        $candidate = $this->createCandidate('Screening');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * 10. Rejected -> Hired rejected
     */
    public function test_10_rejected_to_hired_rejected(): void
    {
        $candidate = $this->createCandidate('Rejected');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    /**
     * 11. Hired -> another recruitment status rejected (terminal state)
     */
    public function test_11_hired_to_another_recruitment_status_rejected(): void
    {
        $candidate = $this->createCandidate('Hired');

        $responseShortlisted = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Shortlisted',
        ]);
        $responseShortlisted->assertStatus(422)->assertJsonValidationErrors('status');

        $responseScreening = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);
        $responseScreening->assertStatus(422)->assertJsonValidationErrors('status');
    }

    /**
     * 12. Rejection without remarks rejected
     */
    public function test_12_rejection_without_remarks_rejected(): void
    {
        $candidate = $this->createCandidate('Screening');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Rejected',
            'remarks' => '', // Empty remarks
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('remarks');
    }

    /**
     * 13. Status history created for every successful transition
     */
    public function test_13_status_history_created_for_every_successful_transition(): void
    {
        $candidate = $this->createCandidate('New');

        // Transition 1: New -> Screening
        $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
            'remarks' => 'Reviewing profile',
        ])->assertStatus(200);

        // Transition 2: Screening -> Shortlisted
        $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Shortlisted',
            'remarks' => 'Candidate shortlisted for interviews',
        ])->assertStatus(200);

        $this->assertEquals(3, $candidate->fresh()->statusHistories()->count());
    }

    /**
     * 14. Status history preserves previous records in chronological order
     */
    public function test_14_status_history_preserves_previous_records(): void
    {
        $candidate = $this->createCandidate('New');

        $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
            'remarks' => 'First transition',
        ]);

        $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Shortlisted',
            'remarks' => 'Second transition',
        ]);

        $histories = $candidate->fresh()->statusHistories()->get();

        $this->assertCount(3, $histories);
        $this->assertNull($histories[0]->from_status);
        $this->assertEquals('New', $histories[0]->to_status);

        $this->assertEquals('New', $histories[1]->from_status);
        $this->assertEquals('Screening', $histories[1]->to_status);
        $this->assertEquals('First transition', $histories[1]->remarks);

        $this->assertEquals('Screening', $histories[2]->from_status);
        $this->assertEquals('Shortlisted', $histories[2]->to_status);
        $this->assertEquals('Second transition', $histories[2]->remarks);
    }

    /**
     * 15. Audit log created for candidate.status_changed
     */
    public function test_15_audit_log_created(): void
    {
        $candidate = $this->createCandidate('New');

        $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
            'remarks' => 'Move to screening',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'candidate.status_changed',
            'user_id' => $this->hrAdmin->id,
        ]);
    }

    /**
     * 16. Candidate status + history rollback together on transaction failure
     */
    public function test_16_candidate_status_and_history_rollback_together_on_failure(): void
    {
        $candidate = $this->createCandidate('New');
        $initialHistoryCount = $candidate->statusHistories()->count();

        // Attempting an invalid transition
        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired', // Invalid from New
        ]);

        $response->assertStatus(422);

        $this->assertEquals('New', $candidate->fresh()->status);
        $this->assertEquals($initialHistoryCount, $candidate->fresh()->statusHistories()->count());
    }

    /**
     * 17. Hired blocked when no completed interview exists
     */
    public function test_17_hired_blocked_when_no_completed_interview_exists(): void
    {
        $candidate = $this->createCandidate('Shortlisted');

        // Candidate has a scheduled interview, but NOT completed
        Interview::create([
            'candidate_id' => $candidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => now()->addDays(2),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Scheduled',
            'created_by' => $this->hrAdmin->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $errorMessage = $response->json('errors.status.0');
        $this->assertStringContainsString('completed interview', $errorMessage);
    }

    /**
     * 18. Hired blocked when completed interview has no feedback
     */
    public function test_18_hired_blocked_when_completed_interview_has_no_feedback(): void
    {
        $candidate = $this->createCandidate('Shortlisted');

        // Completed interview without feedback
        Interview::create([
            'candidate_id' => $candidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => now()->subDays(2),
            'duration_minutes' => 60,
            'mode' => 'Online',
            'status' => 'Completed',
            'created_by' => $this->hrAdmin->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $errorMessage = $response->json('errors.status.0');
        $this->assertStringContainsString('structured feedback', $errorMessage);
    }

    /**
     * 19. Hired succeeds with completed interview + feedback
     */
    public function test_19_hired_succeeds_with_completed_interview_and_feedback(): void
    {
        $candidate = $this->createCandidate('Shortlisted');

        // Completed interview
        $interview = Interview::create([
            'candidate_id' => $candidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'interview_type' => 'Technical',
            'interview_round' => 1,
            'scheduled_at' => now()->subDay(),
            'duration_minutes' => 45,
            'mode' => 'Online',
            'status' => 'Completed',
            'created_by' => $this->hrAdmin->id,
        ]);

        // Feedback
        InterviewFeedback::create([
            'interview_id' => $interview->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'overall_rating' => 4,
            'recommendation' => 'Hire',
            'comments' => 'Strong fit for team',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
            'remarks' => 'Selected by interviewing panel',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Hired');

        $this->assertEquals('Hired', $candidate->fresh()->status);
    }

    /**
     * 20. Existing interview and feedback records are not modified during status transition
     */
    public function test_20_existing_interview_and_feedback_records_not_modified(): void
    {
        $candidate = $this->createCandidate('Shortlisted');

        $interview = Interview::create([
            'candidate_id' => $candidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'interview_type' => 'Final',
            'interview_round' => 2,
            'scheduled_at' => now()->subHours(5),
            'duration_minutes' => 30,
            'mode' => 'Online',
            'status' => 'Completed',
            'remarks' => 'Final round remarks',
            'created_by' => $this->hrAdmin->id,
        ]);

        $feedback = InterviewFeedback::create([
            'interview_id' => $interview->id,
            'interviewer_employee_id' => $this->interviewerEmp->id,
            'overall_rating' => 5,
            'recommendation' => 'Strong Hire',
            'strengths' => 'Original strengths text',
            'comments' => 'Original feedback comments',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Hired',
            'remarks' => 'Approved offer',
        ])->assertStatus(200);

        // Verify interview unchanged
        $this->assertEquals('Completed', $interview->fresh()->status);
        $this->assertEquals('Final round remarks', $interview->fresh()->remarks);

        // Verify feedback unchanged
        $this->assertEquals('Original strengths text', $feedback->fresh()->strengths);
        $this->assertEquals('Original feedback comments', $feedback->fresh()->comments);
    }

    /**
     * 21. HR Admin authorized
     */
    public function test_21_hr_admin_authorized(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(200);
    }

    /**
     * 22. Super Admin authorized
     */
    public function test_22_super_admin_authorized(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->superAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(200);
    }

    /**
     * 23. HR Executive authorized
     */
    public function test_23_hr_executive_authorized(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->hrExecutive)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(200);
    }

    /**
     * 24. Manager unauthorized
     */
    public function test_24_manager_unauthorized(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->managerUser)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 25. Employee unauthorized
     */
    public function test_25_employee_unauthorized(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->employeeUser)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 26. Finance/Payroll unauthorized
     */
    public function test_26_finance_payroll_unauthorized(): void
    {
        $candidate = $this->createCandidate('New');

        $response = $this->actingAs($this->payrollUser)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(403);
    }

    /**
     * 27. Status history access follows candidate authorization
     */
    public function test_27_status_history_access_follows_candidate_authorization(): void
    {
        $candidate = $this->createCandidate('New');

        // HR Admin can view history
        $resAdmin = $this->actingAs($this->hrAdmin)->getJson("/api/candidates/{$candidate->id}/status-history");
        $resAdmin->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'from_status', 'to_status', 'changed_at']]]);

        // HR Executive can view history
        $resExec = $this->actingAs($this->hrExecutive)->getJson("/api/candidates/{$candidate->id}/status-history");
        $resExec->assertStatus(200);

        // Employee cannot view history
        $resEmp = $this->actingAs($this->employeeUser)->getJson("/api/candidates/{$candidate->id}/status-history");
        $resEmp->assertStatus(403);

        // Payroll cannot view history
        $resPayroll = $this->actingAs($this->payrollUser)->getJson("/api/candidates/{$candidate->id}/status-history");
        $resPayroll->assertStatus(403);
    }

    /**
     * 28. Same status transition rejected
     */
    public function test_28_same_status_transition_rejected(): void
    {
        $candidate = $this->createCandidate('Screening');

        $response = $this->actingAs($this->hrAdmin)->patchJson("/api/candidates/{$candidate->id}/status", [
            'status' => 'Screening',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
