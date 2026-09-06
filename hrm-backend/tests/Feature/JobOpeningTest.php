<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\JobOpening;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOpeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $hrExecutive;
    protected User $managerUser;
    protected User $employeeUser;
    protected User $payrollUser;

    protected Department $deptEngineering;
    protected Department $deptMarketing;
    protected Designation $desigDeveloper;

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
            'description' => 'Software & Hardware Engineering',
        ]);

        $this->deptMarketing = Department::create([
            'name' => 'Marketing',
            'description' => 'Marketing & Growth',
        ]);

        $this->desigDeveloper = Designation::create([
            'title' => 'Senior Backend Developer',
            'description' => 'Leads backend architecture and APIs',
        ]);
    }

    /**
     * 1. Authorized HR user can create job opening.
     */
    public function test_1_authorized_hr_user_can_create_job_opening(): void
    {
        $payload = [
            'title' => 'Senior Laravel Engineer',
            'job_code' => 'JOB-2026-ENG01',
            'department_id' => $this->deptEngineering->id,
            'designation_id' => $this->desigDeveloper->id,
            'employment_type' => 'Full Time',
            'location' => 'San Francisco, CA',
            'openings_count' => 3,
            'description' => 'We are seeking an experienced Laravel engineer.',
            'requirements' => '5+ years PHP, Laravel, MySQL.',
            'responsibilities' => 'Design APIs, optimize performance, mentor juniors.',
            'experience_min' => 4,
            'experience_max' => 8,
            'salary_min' => 90000,
            'salary_max' => 130000,
            'application_deadline' => '2026-12-31',
            'status' => 'Open',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Job opening created successfully')
            ->assertJsonPath('data.title', 'Senior Laravel Engineer')
            ->assertJsonPath('data.job_code', 'JOB-2026-ENG01')
            ->assertJsonPath('data.status', 'Open')
            ->assertJsonPath('data.openings_count', 3);

        $this->assertDatabaseHas('job_openings', [
            'job_code' => 'JOB-2026-ENG01',
            'title' => 'Senior Laravel Engineer',
            'department_id' => $this->deptEngineering->id,
            'status' => 'Open',
        ]);
    }

    /**
     * 2. Authorized HR user can view job openings.
     */
    public function test_2_authorized_hr_user_can_view_job_openings(): void
    {
        $job = JobOpening::create([
            'title' => 'Frontend Specialist',
            'job_code' => 'JOB-2026-FE01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 2,
            'description' => 'React expert needed.',
            'status' => 'Open',
        ]);

        $listResponse = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings');
        $listResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);

        $showResponse = $this->actingAs($this->hrAdmin)->getJson("/api/job-openings/{$job->id}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('title', 'Frontend Specialist')
            ->assertJsonPath('job_code', 'JOB-2026-FE01');
    }

    /**
     * 3. Authorized HR user can update job opening.
     */
    public function test_3_authorized_hr_user_can_update_job_opening(): void
    {
        $job = JobOpening::create([
            'title' => 'QA Engineer',
            'job_code' => 'JOB-2026-QA01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Contract',
            'location' => 'Austin, TX',
            'openings_count' => 1,
            'description' => 'Automation QA required.',
            'status' => 'Draft',
        ]);

        $updatePayload = [
            'title' => 'Senior QA Automation Lead',
            'job_code' => 'JOB-2026-QA01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Austin, TX',
            'openings_count' => 2,
            'description' => 'Updated: Senior Automation QA lead required.',
            'status' => 'Open',
        ];

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/job-openings/{$job->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Job opening updated successfully')
            ->assertJsonPath('data.title', 'Senior QA Automation Lead')
            ->assertJsonPath('data.status', 'Open')
            ->assertJsonPath('data.employment_type', 'Full Time')
            ->assertJsonPath('data.openings_count', 2);

        $this->assertDatabaseHas('job_openings', [
            'id' => $job->id,
            'title' => 'Senior QA Automation Lead',
            'status' => 'Open',
            'openings_count' => 2,
        ]);
    }

    /**
     * 4. Authorized HR user can delete job opening.
     */
    public function test_4_authorized_hr_user_can_delete_job_opening(): void
    {
        $job = JobOpening::create([
            'title' => 'Temporary Content Writer',
            'job_code' => 'JOB-2026-WR01',
            'department_id' => $this->deptMarketing->id,
            'employment_type' => 'Contract',
            'location' => 'New York, NY',
            'openings_count' => 1,
            'description' => 'Draft copy for product launch.',
            'status' => 'Closed',
        ]);

        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/job-openings/{$job->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Job opening deleted successfully');

        $this->assertSoftDeleted('job_openings', [
            'id' => $job->id,
        ]);
    }

    /**
     * 5. Unauthorized Employee receives 403.
     */
    public function test_5_unauthorized_employee_receives_403(): void
    {
        $job = JobOpening::create([
            'title' => 'SecOps Engineer',
            'job_code' => 'JOB-2026-SEC01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Security engineer.',
            'status' => 'Open',
        ]);

        // GET list
        $this->actingAs($this->employeeUser)->getJson('/api/job-openings')
            ->assertStatus(403);

        // GET show
        $this->actingAs($this->employeeUser)->getJson("/api/job-openings/{$job->id}")
            ->assertStatus(403);

        // POST create
        $this->actingAs($this->employeeUser)->postJson('/api/job-openings', [
            'title' => 'Hacker',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Unauthorized creation',
            'status' => 'Draft',
        ])->assertStatus(403);

        // PUT update
        $this->actingAs($this->employeeUser)->putJson("/api/job-openings/{$job->id}", [
            'title' => 'Hacked Job',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Updated by employee',
            'status' => 'Open',
        ])->assertStatus(403);

        // DELETE
        $this->actingAs($this->employeeUser)->deleteJson("/api/job-openings/{$job->id}")
            ->assertStatus(403);
    }

    /**
     * 6. Finance/Payroll receives 403.
     */
    public function test_6_finance_payroll_receives_403(): void
    {
        $job = JobOpening::create([
            'title' => 'DevOps Specialist',
            'job_code' => 'JOB-2026-DO01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Kubernetes specialist.',
            'status' => 'Open',
        ]);

        $this->actingAs($this->payrollUser)->getJson('/api/job-openings')
            ->assertStatus(403);

        $this->actingAs($this->payrollUser)->postJson('/api/job-openings', [
            'title' => 'Accountant',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Payroll creation attempt',
            'status' => 'Draft',
        ])->assertStatus(403);

        $this->actingAs($this->payrollUser)->deleteJson("/api/job-openings/{$job->id}")
            ->assertStatus(403);
    }

    /**
     * 7. Invalid department is rejected.
     */
    public function test_7_invalid_department_is_rejected(): void
    {
        $payload = [
            'title' => 'Product Manager',
            'department_id' => 999999, // Non-existent department
            'employment_type' => 'Full Time',
            'location' => 'Chicago, IL',
            'openings_count' => 1,
            'description' => 'PM opening',
            'status' => 'Draft',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['department_id']);
    }

    /**
     * 8. Invalid employment type is rejected.
     */
    public function test_8_invalid_employment_type_is_rejected(): void
    {
        $payload = [
            'title' => 'Freelance Designer',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Uncontrolled Random Text', // Invalid employment type
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Design work',
            'status' => 'Draft',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employment_type']);
    }

    /**
     * 9. Invalid status is rejected.
     */
    public function test_9_invalid_status_is_rejected(): void
    {
        $payload = [
            'title' => 'Site Reliability Engineer',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Seattle, WA',
            'openings_count' => 1,
            'description' => 'SRE role',
            'status' => 'InvalidCustomStatus', // Invalid status
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    /**
     * 10. Invalid openings count is rejected.
     */
    public function test_10_invalid_openings_count_is_rejected(): void
    {
        $payloadZero = [
            'title' => 'Data Analyst',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Boston, MA',
            'openings_count' => 0, // Must be at least 1
            'description' => 'Data team analyst',
            'status' => 'Draft',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payloadZero);
        $response->assertStatus(422)->assertJsonValidationErrors(['openings_count']);

        $payloadNegative = array_merge($payloadZero, ['openings_count' => -2]);
        $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payloadNegative)
            ->assertStatus(422)->assertJsonValidationErrors(['openings_count']);
    }

    /**
     * 11. Invalid experience/salary range is rejected.
     */
    public function test_11_invalid_experience_and_salary_ranges_are_rejected(): void
    {
        // experience_max less than experience_min
        $payloadExp = [
            'title' => 'Mobile Developer',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'iOS/Android dev',
            'experience_min' => 5,
            'experience_max' => 2, // invalid
            'status' => 'Draft',
        ];

        $responseExp = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payloadExp);
        $responseExp->assertStatus(422)->assertJsonValidationErrors(['experience_max']);

        // salary_max less than salary_min
        $payloadSal = [
            'title' => 'Mobile Developer',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'iOS/Android dev',
            'salary_min' => 100000,
            'salary_max' => 60000, // invalid
            'status' => 'Draft',
        ];

        $responseSal = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', $payloadSal);
        $responseSal->assertStatus(422)->assertJsonValidationErrors(['salary_max']);
    }

    /**
     * 12. Search works.
     */
    public function test_12_search_by_title_and_job_code_works(): void
    {
        JobOpening::create([
            'title' => 'Fullstack Architect Unicorn',
            'job_code' => 'JOB-2026-ARCH01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Denver, CO',
            'openings_count' => 1,
            'description' => 'Architect level specialist.',
            'status' => 'Open',
        ]);

        JobOpening::create([
            'title' => 'Brand Copywriter',
            'job_code' => 'JOB-2026-MKT01',
            'department_id' => $this->deptMarketing->id,
            'employment_type' => 'Part Time',
            'location' => 'Miami, FL',
            'openings_count' => 1,
            'description' => 'Brand stories.',
            'status' => 'Open',
        ]);

        // Search by title term
        $response1 = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?search=Unicorn');
        $response1->assertStatus(200);
        $this->assertCount(1, $response1->json('data'));
        $this->assertEquals('Fullstack Architect Unicorn', $response1->json('data.0.title'));

        // Search by job code
        $response2 = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?search=ARCH01');
        $response2->assertStatus(200);
        $this->assertCount(1, $response2->json('data'));
        $this->assertEquals('JOB-2026-ARCH01', $response2->json('data.0.job_code'));
    }

    /**
     * 13. Status filter works.
     */
    public function test_13_status_filter_works(): void
    {
        JobOpening::create([
            'title' => 'Open Position 1',
            'job_code' => 'JOB-OP-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Description',
            'status' => 'Open',
        ]);

        JobOpening::create([
            'title' => 'Draft Position 1',
            'job_code' => 'JOB-DR-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Description',
            'status' => 'Draft',
        ]);

        JobOpening::create([
            'title' => 'Closed Position 1',
            'job_code' => 'JOB-CL-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Description',
            'status' => 'Closed',
        ]);

        $respOpen = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?status=Open');
        $respOpen->assertStatus(200);
        $this->assertCount(1, $respOpen->json('data'));
        $this->assertEquals('Open', $respOpen->json('data.0.status'));

        $respDraft = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?status=Draft');
        $respDraft->assertStatus(200);
        $this->assertCount(1, $respDraft->json('data'));
        $this->assertEquals('Draft', $respDraft->json('data.0.status'));

        $respClosed = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?status=Closed');
        $respClosed->assertStatus(200);
        $this->assertCount(1, $respClosed->json('data'));
        $this->assertEquals('Closed', $respClosed->json('data.0.status'));
    }

    /**
     * 14. Department filter works.
     */
    public function test_14_department_filter_works(): void
    {
        JobOpening::create([
            'title' => 'Eng Position',
            'job_code' => 'JOB-ENG-02',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Description',
            'status' => 'Open',
        ]);

        JobOpening::create([
            'title' => 'Mkt Position',
            'job_code' => 'JOB-MKT-02',
            'department_id' => $this->deptMarketing->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Description',
            'status' => 'Open',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/job-openings?department_id={$this->deptEngineering->id}");
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->deptEngineering->id, $response->json('data.0.department_id'));
    }

    /**
     * 15. Pagination works.
     */
    public function test_15_pagination_works(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            JobOpening::create([
                'title' => "Batch Job Opening {$i}",
                'job_code' => "JOB-BATCH-{$i}",
                'department_id' => $this->deptEngineering->id,
                'employment_type' => 'Full Time',
                'location' => 'Remote',
                'openings_count' => 1,
                'description' => 'Batch creation test',
                'status' => 'Open',
            ]);
        }

        $page1 = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?per_page=5&page=1');
        $page1->assertStatus(200)
            ->assertJsonPath('per_page', 5)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('total', 15);
        $this->assertCount(5, $page1->json('data'));

        $page2 = $this->actingAs($this->hrAdmin)->getJson('/api/job-openings?per_page=5&page=2');
        $page2->assertStatus(200)
            ->assertJsonPath('current_page', 2);
        $this->assertCount(5, $page2->json('data'));
    }

    /**
     * 16. Audit log is created for important changes.
     */
    public function test_16_audit_log_is_created_for_important_changes(): void
    {
        // Creation audit
        $createRes = $this->actingAs($this->hrAdmin)->postJson('/api/job-openings', [
            'title' => 'Audited Job Title',
            'job_code' => 'JOB-AUDIT-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'San Jose, CA',
            'openings_count' => 2,
            'description' => 'Audit test description',
            'status' => 'Draft',
        ]);
        $createRes->assertStatus(201);
        $jobId = $createRes->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'created',
            'entity_type' => JobOpening::class,
            'entity_id' => $jobId,
        ]);

        // Status update audit
        $updateRes = $this->actingAs($this->hrAdmin)->putJson("/api/job-openings/{$jobId}", [
            'title' => 'Audited Job Title',
            'job_code' => 'JOB-AUDIT-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'San Jose, CA',
            'openings_count' => 2,
            'description' => 'Audit test description',
            'status' => 'Open',
        ]);
        $updateRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'updated',
            'entity_type' => JobOpening::class,
            'entity_id' => $jobId,
        ]);

        // Deletion audit
        $deleteRes = $this->actingAs($this->hrAdmin)->deleteJson("/api/job-openings/{$jobId}");
        $deleteRes->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'entity_type' => JobOpening::class,
            'entity_id' => $jobId,
        ]);
    }

    /**
     * 17. HR Executive can view, create, and update, but CANNOT delete.
     */
    public function test_17_hr_executive_can_update_but_cannot_delete(): void
    {
        $job = JobOpening::create([
            'title' => 'HR Exec Managed Job',
            'job_code' => 'JOB-HREX-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Internship',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Internship opening',
            'status' => 'Draft',
        ]);

        // HR Executive can update
        $this->actingAs($this->hrExecutive)->putJson("/api/job-openings/{$job->id}", [
            'title' => 'HR Exec Managed Job Updated',
            'job_code' => 'JOB-HREX-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Internship',
            'location' => 'Remote',
            'openings_count' => 2,
            'description' => 'Internship opening updated',
            'status' => 'Open',
        ])->assertStatus(200);

        // HR Executive CANNOT delete (403)
        $this->actingAs($this->hrExecutive)->deleteJson("/api/job-openings/{$job->id}")
            ->assertStatus(403);
    }

    /**
     * 18. Manager is rejected with 403 on job management endpoints.
     */
    public function test_18_manager_receives_403(): void
    {
        $job = JobOpening::create([
            'title' => 'Engineering Manager',
            'job_code' => 'JOB-MGR-01',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Team manager',
            'status' => 'Open',
        ]);

        $this->actingAs($this->managerUser)->getJson('/api/job-openings')
            ->assertStatus(403);

        $this->actingAs($this->managerUser)->postJson('/api/job-openings', [
            'title' => 'Attempt by Manager',
            'department_id' => $this->deptEngineering->id,
            'employment_type' => 'Full Time',
            'location' => 'Remote',
            'openings_count' => 1,
            'description' => 'Desc',
            'status' => 'Draft',
        ])->assertStatus(403);

        $this->actingAs($this->managerUser)->deleteJson("/api/job-openings/{$job->id}")
            ->assertStatus(403);
    }
}
