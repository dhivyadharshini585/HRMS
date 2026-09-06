<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\JobOpening;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CandidateTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $hrExecutive;
    protected User $managerUser;
    protected User $employeeUser;
    protected User $payrollUser;

    protected Department $deptEngineering;
    protected Designation $desigDeveloper;
    protected JobOpening $jobOpening1;
    protected JobOpening $jobOpening2;

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
            'title' => 'Backend Developer',
            'description' => 'APIs and Systems',
        ]);

        $this->jobOpening1 = JobOpening::create([
            'title' => 'Senior Backend Engineer',
            'job_code' => 'JOB-ENG-001',
            'department_id' => $this->deptEngineering->id,
            'designation_id' => $this->desigDeveloper->id,
            'employment_type' => 'Full Time',
            'location' => 'Austin, TX',
            'openings_count' => 2,
            'description' => 'Backend engineer opening',
            'status' => 'Open',
        ]);

        $this->jobOpening2 = JobOpening::create([
            'title' => 'DevOps Specialist',
            'job_code' => 'JOB-ENG-002',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'DevOps opening',
            'status' => 'Open',
        ]);
    }

    /**
     * 1. Authorized HR can create candidate.
     */
    public function test_1_authorized_hr_can_create_candidate(): void
    {
        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1 555-0199',
            'alternate_phone' => '+1 555-0200',
            'date_of_birth' => '1995-06-15',
            'gender' => 'Male',
            'current_location' => 'Austin, TX',
            'address' => '123 Tech Lane',
            'highest_qualification' => 'B.S. Computer Science',
            'total_experience_years' => 5.5,
            'current_company' => 'Acme Corp',
            'current_designation' => 'Software Engineer II',
            'expected_salary' => 115000,
            'notice_period_days' => 30,
            'source' => 'LinkedIn',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
            'notes' => 'Strong background in Laravel and distributed systems.',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Candidate created successfully')
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.last_name', 'Doe')
            ->assertJsonPath('data.email', 'john.doe@example.com')
            ->assertJsonPath('data.status', 'New');

        $this->assertDatabaseHas('candidates', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'job_opening_id' => $this->jobOpening1->id,
        ]);
    }

    /**
     * 2. Authorized HR can view candidates list.
     */
    public function test_2_authorized_hr_can_view_candidates(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-00001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'phone' => '+1 555-1111',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/candidates');
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * 3. Authorized HR can view candidate details.
     */
    public function test_3_authorized_hr_can_view_candidate_details(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-00002',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob@example.com',
            'phone' => '+1 555-2222',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Screening',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/candidates/{$candidate->id}");
        $response->assertStatus(200)
            ->assertJsonPath('first_name', 'Bob')
            ->assertJsonPath('candidate_code', 'CAN-00002')
            ->assertJsonPath('status', 'Screening');
    }

    /**
     * 4. Authorized HR can update candidate.
     */
    public function test_4_authorized_hr_can_update_candidate(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-00003',
            'first_name' => 'Carol',
            'last_name' => 'White',
            'email' => 'carol@example.com',
            'phone' => '+1 555-3333',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $updatePayload = [
            'first_name' => 'Carol',
            'last_name' => 'White',
            'email' => 'carol.white@updated.com',
            'phone' => '+1 555-3333',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Shortlisted',
            'total_experience_years' => 6,
        ];

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/candidates/{$candidate->id}", $updatePayload);
        $response->assertStatus(200)
            ->assertJsonPath('message', 'Candidate updated successfully')
            ->assertJsonPath('data.status', 'Shortlisted')
            ->assertJsonPath('data.email', 'carol.white@updated.com');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'status' => 'Shortlisted',
            'email' => 'carol.white@updated.com',
        ]);
    }

    /**
     * 5. Authorized HR can delete candidate.
     */
    public function test_5_authorized_hr_can_delete_candidate(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-00004',
            'first_name' => 'Dave',
            'last_name' => 'Brown',
            'email' => 'dave@example.com',
            'phone' => '+1 555-4444',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Rejected',
        ]);

        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$candidate->id}");
        $response->assertStatus(200)
            ->assertJsonPath('message', 'Candidate deleted successfully');

        $this->assertSoftDeleted('candidates', [
            'id' => $candidate->id,
        ]);
    }

    /**
     * 6. Unauthorized Employee receives 403.
     */
    public function test_6_unauthorized_employee_receives_403(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-00005',
            'first_name' => 'Eva',
            'last_name' => 'Green',
            'email' => 'eva@example.com',
            'phone' => '+1 555-5555',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $this->actingAs($this->employeeUser)->getJson('/api/candidates')->assertStatus(403);
        $this->actingAs($this->employeeUser)->getJson("/api/candidates/{$candidate->id}")->assertStatus(403);
        $this->actingAs($this->employeeUser)->postJson('/api/candidates', [
            'first_name' => 'Unauthorized',
            'last_name' => 'User',
            'email' => 'unauth@example.com',
            'phone' => '123',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ])->assertStatus(403);
        $this->actingAs($this->employeeUser)->deleteJson("/api/candidates/{$candidate->id}")->assertStatus(403);
    }

    /**
     * 7. Finance/Payroll receives 403.
     */
    public function test_7_finance_payroll_receives_403(): void
    {
        $this->actingAs($this->payrollUser)->getJson('/api/candidates')->assertStatus(403);
        $this->actingAs($this->payrollUser)->postJson('/api/candidates', [
            'first_name' => 'Payroll',
            'last_name' => 'Applicant',
            'email' => 'payroll.app@example.com',
            'phone' => '123',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ])->assertStatus(403);
    }

    /**
     * 8. Manager receives 403 if not authorized.
     */
    public function test_8_manager_receives_403(): void
    {
        $this->actingAs($this->managerUser)->getJson('/api/candidates')->assertStatus(403);
        $this->actingAs($this->managerUser)->postJson('/api/candidates', [
            'first_name' => 'Manager',
            'last_name' => 'Candidate',
            'email' => 'mgr.cand@example.com',
            'phone' => '123',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ])->assertStatus(403);
    }

    /**
     * 9. Invalid job opening is rejected.
     */
    public function test_9_invalid_job_opening_is_rejected(): void
    {
        $payload = [
            'first_name' => 'Frank',
            'last_name' => 'Wright',
            'email' => 'frank@example.com',
            'phone' => '+1 555-6666',
            'job_opening_id' => 999999, // Nonexistent
            'status' => 'New',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_opening_id']);
    }

    /**
     * 10. Invalid email is rejected.
     */
    public function test_10_invalid_email_is_rejected(): void
    {
        $payload = [
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'email' => 'not-an-email-address',
            'phone' => '+1 555-7777',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * 11. Invalid status is rejected.
     */
    public function test_11_invalid_status_is_rejected(): void
    {
        $payload = [
            'first_name' => 'Harry',
            'last_name' => 'Potter',
            'email' => 'harry@example.com',
            'phone' => '+1 555-8888',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'MagicalStatus', // Invalid
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * 12. Negative experience is rejected.
     */
    public function test_12_negative_experience_is_rejected(): void
    {
        $payload = [
            'first_name' => 'Ian',
            'last_name' => 'Malcolm',
            'email' => 'ian@example.com',
            'phone' => '+1 555-9999',
            'job_opening_id' => $this->jobOpening1->id,
            'total_experience_years' => -3,
            'status' => 'New',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_experience_years']);
    }

    /**
     * 13. Negative expected salary is rejected.
     */
    public function test_13_negative_expected_salary_is_rejected(): void
    {
        $payload = [
            'first_name' => 'Jack',
            'last_name' => 'Sparrow',
            'email' => 'jack@example.com',
            'phone' => '+1 555-1234',
            'job_opening_id' => $this->jobOpening1->id,
            'expected_salary' => -50000,
            'status' => 'New',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expected_salary']);
    }

    /**
     * 14. Negative notice period is rejected.
     */
    public function test_14_negative_notice_period_is_rejected(): void
    {
        $payload = [
            'first_name' => 'Kelly',
            'last_name' => 'Kapoor',
            'email' => 'kelly@example.com',
            'phone' => '+1 555-4321',
            'job_opening_id' => $this->jobOpening1->id,
            'notice_period_days' => -15,
            'status' => 'New',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notice_period_days']);
    }

    /**
     * 15. Search by candidate name works.
     */
    public function test_15_search_by_candidate_name_works(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-10001',
            'first_name' => 'Zendaya',
            'last_name' => 'Coleman',
            'email' => 'zendaya@example.com',
            'phone' => '+1 555-0001',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        Candidate::create([
            'candidate_code' => 'CAN-10002',
            'first_name' => 'Tom',
            'last_name' => 'Holland',
            'email' => 'tom@example.com',
            'phone' => '+1 555-0002',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?search=Zendaya');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Zendaya', $response->json('data.0.first_name'));
    }

    /**
     * 16. Search by candidate code works.
     */
    public function test_16_search_by_candidate_code_works(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-SPECIAL-99',
            'first_name' => 'Special',
            'last_name' => 'Candidate',
            'email' => 'special@example.com',
            'phone' => '+1 555-0099',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?search=SPECIAL-99');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('CAN-SPECIAL-99', $response->json('data.0.candidate_code'));
    }

    /**
     * 17. Search by email works.
     */
    public function test_17_search_by_email_works(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-20001',
            'first_name' => 'Unique',
            'last_name' => 'Emailer',
            'email' => 'findme@uniqueemail.com',
            'phone' => '+1 555-7890',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?search=findme@uniqueemail.com');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('findme@uniqueemail.com', $response->json('data.0.email'));
    }

    /**
     * 18. Status filter works.
     */
    public function test_18_status_filter_works(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-STAT-1',
            'first_name' => 'Stat1',
            'last_name' => 'Test',
            'email' => 'stat1@example.com',
            'phone' => '111',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Shortlisted',
        ]);

        Candidate::create([
            'candidate_code' => 'CAN-STAT-2',
            'first_name' => 'Stat2',
            'last_name' => 'Test',
            'email' => 'stat2@example.com',
            'phone' => '222',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Rejected',
        ]);

        $responseShortlisted = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?status=Shortlisted');
        $responseShortlisted->assertStatus(200);
        $this->assertCount(1, $responseShortlisted->json('data'));
        $this->assertEquals('Shortlisted', $responseShortlisted->json('data.0.status'));

        $responseRejected = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?status=Rejected');
        $responseRejected->assertStatus(200);
        $this->assertCount(1, $responseRejected->json('data'));
        $this->assertEquals('Rejected', $responseRejected->json('data.0.status'));
    }

    /**
     * 19. Job opening filter works.
     */
    public function test_19_job_opening_filter_works(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-JOB-1',
            'first_name' => 'Job1',
            'last_name' => 'Cand',
            'email' => 'cand1@job1.com',
            'phone' => '111',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        Candidate::create([
            'candidate_code' => 'CAN-JOB-2',
            'first_name' => 'Job2',
            'last_name' => 'Cand',
            'email' => 'cand2@job2.com',
            'phone' => '222',
            'job_opening_id' => $this->jobOpening2->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/candidates?job_opening_id={$this->jobOpening1->id}");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->jobOpening1->id, $response->json('data.0.job_opening_id'));
    }

    /**
     * 20. Pagination works.
     */
    public function test_20_pagination_works(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            Candidate::create([
                'candidate_code' => "CAN-PAG-{$i}",
                'first_name' => "Candidate {$i}",
                'last_name' => 'Batch',
                'email' => "batch{$i}@example.com",
                'phone' => "555-{$i}",
                'job_opening_id' => $this->jobOpening1->id,
                'status' => 'New',
            ]);
        }

        $page1 = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?per_page=5&page=1');
        $page1->assertStatus(200)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('total', 12);
        $this->assertCount(5, $page1->json('data'));

        $page2 = $this->actingAs($this->hrAdmin)->getJson('/api/candidates?per_page=5&page=2');
        $page2->assertStatus(200)
            ->assertJsonPath('current_page', 2);
        $this->assertCount(5, $page2->json('data'));
    }

    /**
     * 21. Candidate code is unique.
     */
    public function test_21_candidate_code_is_unique(): void
    {
        Candidate::create([
            'candidate_code' => 'CAN-DUPE-CODE',
            'first_name' => 'Original',
            'last_name' => 'User',
            'email' => 'original@example.com',
            'phone' => '111',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', [
            'candidate_code' => 'CAN-DUPE-CODE', // duplicate
            'first_name' => 'Duplicate',
            'last_name' => 'User',
            'email' => 'dupe@example.com',
            'phone' => '222',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['candidate_code']);
    }

    /**
     * 22. Audit log is created for important candidate changes.
     */
    public function test_22_audit_log_is_created_for_important_candidate_changes(): void
    {
        // Creation audit
        $createRes = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', [
            'first_name' => 'Audit',
            'last_name' => 'Candidate',
            'email' => 'audit.cand@example.com',
            'phone' => '555-9090',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);
        $createRes->assertStatus(201);
        $candId = $createRes->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => Candidate::class,
            'entity_id' => $candId,
        ]);

        // Status update audit
        $updateRes = $this->actingAs($this->hrAdmin)->putJson("/api/candidates/{$candId}", [
            'first_name' => 'Audit',
            'last_name' => 'Candidate',
            'email' => 'audit.cand@example.com',
            'phone' => '555-9090',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Hired',
        ]);
        $updateRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'entity_type' => Candidate::class,
            'entity_id' => $candId,
        ]);

        // Deletion audit
        $delRes = $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$candId}");
        $delRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'entity_type' => Candidate::class,
            'entity_id' => $candId,
        ]);
    }

    /**
     * 23. Soft-deleted candidate is handled correctly.
     */
    public function test_23_soft_deleted_candidate_is_handled_correctly(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-SOFT-01',
            'first_name' => 'Soft',
            'last_name' => 'Delete',
            'email' => 'soft.delete@example.com',
            'phone' => '123456',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Rejected',
        ]);

        // Delete candidate
        $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$candidate->id}")
            ->assertStatus(200);

        // Should not appear in active list
        $listRes = $this->actingAs($this->hrAdmin)->getJson('/api/candidates');
        $listRes->assertStatus(200);
        $this->assertCount(0, $listRes->json('data'));

        // Email can now be reused by another active candidate because previous one is soft-deleted
        $recreateRes = $this->actingAs($this->hrAdmin)->postJson('/api/candidates', [
            'first_name' => 'New',
            'last_name' => 'Applicant',
            'email' => 'soft.delete@example.com',
            'phone' => '999888',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);
        $recreateRes->assertStatus(201);
    }

    /**
     * 24. Existing Job Opening relationship is returned correctly.
     */
    public function test_24_job_opening_relationship_is_returned_correctly(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-REL-01',
            'first_name' => 'Relation',
            'last_name' => 'Tester',
            'email' => 'relation@example.com',
            'phone' => '555-REL',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/candidates/{$candidate->id}");
        $response->assertStatus(200)
            ->assertJsonPath('job_opening.id', $this->jobOpening1->id)
            ->assertJsonPath('job_opening.title', 'Senior Backend Engineer')
            ->assertJsonPath('job_opening.department.name', 'Engineering');
    }

    /**
     * 25. HR Executive can view, create, and update, but CANNOT delete.
     */
    public function test_25_hr_executive_can_update_but_cannot_delete(): void
    {
        $candidate = Candidate::create([
            'candidate_code' => 'CAN-HREX-01',
            'first_name' => 'Executive',
            'last_name' => 'Managed',
            'email' => 'hrex.cand@example.com',
            'phone' => '555-EXEC',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'New',
        ]);

        // Can update
        $this->actingAs($this->hrExecutive)->putJson("/api/candidates/{$candidate->id}", [
            'first_name' => 'Executive',
            'last_name' => 'Managed',
            'email' => 'hrex.cand@example.com',
            'phone' => '555-EXEC',
            'job_opening_id' => $this->jobOpening1->id,
            'status' => 'Screening',
        ])->assertStatus(200);

        // CANNOT delete (403)
        $this->actingAs($this->hrExecutive)->deleteJson("/api/candidates/{$candidate->id}")
            ->assertStatus(403);
    }
}
