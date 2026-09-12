<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateStatusHistory;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\OfferLetter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $hrExecutive;
    protected User $manager;
    protected User $employee;
    protected User $finance;

    protected JobOpening $job1;
    protected JobOpening $job2;
    protected Department $dept;
    protected Designation $desig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin  = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin     = User::where('email', 'hradmin@hrms.local')->first();
        $this->hrExecutive = User::where('email', 'hrexecutive@hrms.local')->first();
        $this->manager     = User::where('email', 'manager@hrms.local')->first();
        $this->employee    = User::where('email', 'employee@hrms.local')->first();
        $this->finance     = User::where('email', 'payroll@hrms.local')->first();

        $this->dept  = Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        $this->desig = Designation::create(['title' => 'Engineer', 'code' => 'ENG01', 'department_id' => $this->dept->id]);

        $this->job1 = JobOpening::create([
            'job_code'    => 'JOB-001',
            'title'       => 'Backend Developer',
            'department_id'  => $this->dept->id,
            'designation_id' => $this->desig->id,
            'employment_type'=> 'Full Time',
            'location'    => 'Remote',
            'description' => 'Backend Developer role',
            'openings_count' => 2,
            'status'      => 'Open',
            'created_by'  => $this->superAdmin->id,
        ]);

        $this->job2 = JobOpening::create([
            'job_code'    => 'JOB-002',
            'title'       => 'Frontend Developer',
            'department_id'  => $this->dept->id,
            'designation_id' => $this->desig->id,
            'employment_type'=> 'Full Time',
            'location'    => 'Remote',
            'description' => 'Frontend Developer role',
            'openings_count' => 1,
            'status'      => 'Open',
            'created_by'  => $this->superAdmin->id,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Authorization Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_super_admin_can_access_applications_report(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(200)->assertJsonStructure(['summary', 'data', 'meta']);
    }

    public function test_hr_admin_can_access_recruitment_reports(): void
    {
        $response = $this->actingAs($this->hrAdmin)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(200);
    }

    public function test_hr_executive_can_access_recruitment_reports(): void
    {
        $response = $this->actingAs($this->hrExecutive)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(200);
    }

    public function test_manager_cannot_access_recruitment_reports(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(403);
    }

    public function test_employee_cannot_access_recruitment_reports(): void
    {
        $response = $this->actingAs($this->employee)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(403);
    }

    public function test_finance_cannot_access_recruitment_reports(): void
    {
        $response = $this->actingAs($this->finance)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_recruitment_reports(): void
    {
        $response = $this->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(401);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Applications Report Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_applications_report_returns_all_candidates(): void
    {
        Candidate::factory()->count(3)->create(['job_opening_id' => $this->job1->id, 'status' => 'New']);
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job2->id, 'status' => 'Shortlisted']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(5, $response->json('summary.total'));
    }

    public function test_applications_report_filters_by_job_opening(): void
    {
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job1->id]);
        Candidate::factory()->count(3)->create(['job_opening_id' => $this->job2->id]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/reports/recruitment/applications?job_opening_id={$this->job1->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total'));
    }

    public function test_applications_report_filters_by_status(): void
    {
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job1->id, 'status' => 'Hired']);
        Candidate::factory()->count(3)->create(['job_opening_id' => $this->job1->id, 'status' => 'Rejected']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/reports/recruitment/applications?status=Hired');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $response->json('summary.total'));

        // All returned items should have status = Hired
        foreach ($response->json('data') as $item) {
            $this->assertEquals('Hired', $item['status']);
        }
    }

    public function test_applications_report_filters_by_date_range(): void
    {
        // Old candidate - before date range
        $old = Candidate::factory()->create([
            'job_opening_id' => $this->job1->id,
            'created_at'     => now()->subDays(60),
        ]);

        // Recent candidate - within date range
        $recent = Candidate::factory()->create([
            'job_opening_id' => $this->job1->id,
            'created_at'     => now()->subDays(5),
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson(
            '/api/reports/recruitment/applications?date_from=' . now()->subDays(10)->format('Y-m-d')
            . '&date_to=' . now()->format('Y-m-d')
        );

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($recent->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_applications_report_summary_has_by_status_breakdown(): void
    {
        Candidate::factory()->create(['job_opening_id' => $this->job1->id, 'status' => 'New']);
        Candidate::factory()->create(['job_opening_id' => $this->job1->id, 'status' => 'Shortlisted']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/applications');
        $response->assertStatus(200);
        $this->assertArrayHasKey('by_status', $response->json('summary'));
    }

    public function test_applications_report_is_paginated(): void
    {
        Candidate::factory()->count(20)->create(['job_opening_id' => $this->job1->id]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/reports/recruitment/applications?per_page=5');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, $response->json('meta.per_page'));
    }

    public function test_applications_report_validates_invalid_status(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/reports/recruitment/applications?status=InvalidStatus');
        $response->assertStatus(422);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shortlisted Report Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_shortlisted_report_returns_only_shortlisted_candidates(): void
    {
        $shortlisted = Candidate::factory()->count(2)->create([
            'job_opening_id' => $this->job1->id,
            'status'         => 'Shortlisted',
        ]);
        Candidate::factory()->count(2)->create([
            'job_opening_id' => $this->job1->id,
            'status'         => 'New',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/shortlisted');
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total'));
    }

    public function test_shortlisted_report_filters_by_job_opening(): void
    {
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job1->id, 'status' => 'Shortlisted']);
        Candidate::factory()->count(3)->create(['job_opening_id' => $this->job2->id, 'status' => 'Shortlisted']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/reports/recruitment/shortlisted?job_opening_id={$this->job1->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Interviews Report Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_interviews_report_returns_all_interviews(): void
    {
        $candidate = Candidate::factory()->create(['job_opening_id' => $this->job1->id]);
        Interview::factory()->count(3)->create([
            'candidate_id'   => $candidate->id,
            'job_opening_id' => $this->job1->id,
            'status'         => 'Scheduled',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/interviews');
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, $response->json('summary.total'));
    }

    public function test_interviews_report_filters_by_status(): void
    {
        $candidate = Candidate::factory()->create(['job_opening_id' => $this->job1->id]);
        Interview::factory()->count(2)->create([
            'candidate_id'   => $candidate->id,
            'job_opening_id' => $this->job1->id,
            'status'         => 'Completed',
        ]);
        Interview::factory()->count(3)->create([
            'candidate_id'   => $candidate->id,
            'job_opening_id' => $this->job1->id,
            'status'         => 'Scheduled',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/reports/recruitment/interviews?status=Completed');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, $response->json('summary.total'));
        foreach ($response->json('data') as $item) {
            $this->assertEquals('Completed', $item['status']);
        }
    }

    public function test_interviews_report_filters_by_job_opening(): void
    {
        $candidate = Candidate::factory()->create(['job_opening_id' => $this->job1->id]);
        Interview::factory()->count(2)->create([
            'candidate_id'   => $candidate->id,
            'job_opening_id' => $this->job1->id,
        ]);
        Interview::factory()->count(3)->create([
            'candidate_id'   => $candidate->id,
            'job_opening_id' => $this->job2->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/reports/recruitment/interviews?job_opening_id={$this->job1->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total'));
    }

    public function test_interviews_report_has_status_summary_breakdown(): void
    {
        $candidate = Candidate::factory()->create(['job_opening_id' => $this->job1->id]);
        Interview::factory()->create(['candidate_id' => $candidate->id, 'job_opening_id' => $this->job1->id, 'status' => 'Scheduled']);
        Interview::factory()->create(['candidate_id' => $candidate->id, 'job_opening_id' => $this->job1->id, 'status' => 'Completed']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/interviews');
        $response->assertStatus(200);
        $this->assertArrayHasKey('by_status', $response->json('summary'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Selected / Hired Report Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_selected_report_returns_only_hired_candidates(): void
    {
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job1->id, 'status' => 'Hired']);
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job1->id, 'status' => 'Rejected']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/selected');
        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total'));

        foreach ($response->json('data') as $item) {
            $this->assertEquals('Hired', $item['status']);
        }
    }

    public function test_selected_report_filters_by_job_opening(): void
    {
        Candidate::factory()->count(2)->create(['job_opening_id' => $this->job1->id, 'status' => 'Hired']);
        Candidate::factory()->count(3)->create(['job_opening_id' => $this->job2->id, 'status' => 'Hired']);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/reports/recruitment/selected?job_opening_id={$this->job1->id}");

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('summary.total'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Time-to-Hire Report Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_time_to_hire_report_calculates_days_correctly(): void
    {
        // Create candidate 10 days ago
        $candidate = Candidate::factory()->create([
            'job_opening_id' => $this->job1->id,
            'status'         => 'New',
            'created_at'     => now()->subDays(10),
        ]);

        $candidate->update(['status' => 'Hired']);

        // Record the Hired status change 10 days after application
        CandidateStatusHistory::create([
            'candidate_id' => $candidate->id,
            'from_status'  => 'Shortlisted',
            'to_status'    => 'Hired',
            'changed_by'   => $this->superAdmin->id,
            'changed_at'   => now(),
            'remarks'      => 'Test hire',
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/time-to-hire');
        $response->assertStatus(200);

        // Find the candidate in the response
        $item = collect($response->json('data'))->firstWhere('id', $candidate->id);
        $this->assertNotNull($item);
        $this->assertEquals(10, $item['days_to_hire']);
    }

    public function test_time_to_hire_summary_has_avg_min_max(): void
    {
        // Create two hired candidates with different times
        foreach ([5, 15] as $days) {
            $c = Candidate::factory()->create([
                'job_opening_id' => $this->job1->id,
                'status'         => 'New',
                'created_at'     => now()->subDays($days),
            ]);
            $c->update(['status' => 'Hired']);
            CandidateStatusHistory::create([
                'candidate_id' => $c->id,
                'from_status'  => 'Shortlisted',
                'to_status'    => 'Hired',
                'changed_by'   => $this->superAdmin->id,
                'changed_at'   => now(),
            ]);
        }

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/time-to-hire');
        $response->assertStatus(200);
        $this->assertArrayHasKey('avg_days_to_hire', $response->json('summary'));
        $this->assertArrayHasKey('min_days_to_hire', $response->json('summary'));
        $this->assertArrayHasKey('max_days_to_hire', $response->json('summary'));
        $this->assertEquals(5, $response->json('summary.min_days_to_hire'));
        $this->assertEquals(15, $response->json('summary.max_days_to_hire'));
        $this->assertEquals(10.0, $response->json('summary.avg_days_to_hire'));
    }

    public function test_time_to_hire_only_includes_hired_candidates(): void
    {
        $hired = Candidate::factory()->create([
            'job_opening_id' => $this->job1->id,
            'status'         => 'New',
            'created_at'     => now()->subDays(5),
        ]);
        $hired->update(['status' => 'Hired']);
        CandidateStatusHistory::create([
            'candidate_id' => $hired->id,
            'from_status'  => 'Shortlisted',
            'to_status'    => 'Hired',
            'changed_by'   => $this->superAdmin->id,
            'changed_at'   => now(),
        ]);

        Candidate::factory()->create(['job_opening_id' => $this->job1->id, 'status' => 'Rejected']);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/time-to-hire');
        $response->assertStatus(200);

        foreach ($response->json('data') as $item) {
            $this->assertEquals('Hired', $item['status']);
        }
    }

    public function test_time_to_hire_includes_calculation_note(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/time-to-hire');
        $response->assertStatus(200)->assertJsonStructure(['calculation_note', 'summary', 'data', 'meta']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hiring Cost Report Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_hiring_cost_report_returns_accepted_offers_only(): void
    {
        $candidate = Candidate::factory()->create(['job_opening_id' => $this->job1->id, 'status' => 'Hired']);
        OfferLetter::factory()->create([
            'candidate_id'   => $candidate->id,
            'job_opening_id' => $this->job1->id,
            'offer_status'   => 'Accepted',
            'salary_amount'  => 50000.00,
        ]);
        // Non-accepted offer — should be excluded
        $candidate2 = Candidate::factory()->create(['job_opening_id' => $this->job1->id, 'status' => 'New']);
        OfferLetter::factory()->create([
            'candidate_id'   => $candidate2->id,
            'job_opening_id' => $this->job1->id,
            'offer_status'   => 'Draft',
            'salary_amount'  => 60000.00,
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/hiring-cost');
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('summary.total_accepted_offers'));
        $this->assertEquals(50000.00, $response->json('summary.total_offered_salary'));
    }

    public function test_hiring_cost_report_includes_disclaimer(): void
    {
        $response = $this->actingAs($this->superAdmin)->getJson('/api/reports/recruitment/hiring-cost');
        $response->assertStatus(200)->assertJsonStructure(['disclaimer', 'summary', 'data', 'meta']);
        $this->assertNotEmpty($response->json('disclaimer'));
    }

    public function test_hiring_cost_report_filters_by_job_opening(): void
    {
        $c1 = Candidate::factory()->create(['job_opening_id' => $this->job1->id, 'status' => 'Hired']);
        $c2 = Candidate::factory()->create(['job_opening_id' => $this->job2->id, 'status' => 'Hired']);
        OfferLetter::factory()->create(['candidate_id' => $c1->id, 'job_opening_id' => $this->job1->id, 'offer_status' => 'Accepted', 'salary_amount' => 50000]);
        OfferLetter::factory()->create(['candidate_id' => $c2->id, 'job_opening_id' => $this->job2->id, 'offer_status' => 'Accepted', 'salary_amount' => 70000]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/reports/recruitment/hiring-cost?job_opening_id={$this->job1->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('summary.total_accepted_offers'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // All 6 reports: Manager/Employee/Finance blocked on every endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function test_all_report_endpoints_block_unauthorized_roles(): void
    {
        $endpoints = [
            '/api/reports/recruitment/applications',
            '/api/reports/recruitment/shortlisted',
            '/api/reports/recruitment/interviews',
            '/api/reports/recruitment/selected',
            '/api/reports/recruitment/time-to-hire',
            '/api/reports/recruitment/hiring-cost',
        ];

        foreach ($endpoints as $endpoint) {
            foreach ([$this->manager, $this->employee, $this->finance] as $user) {
                $this->actingAs($user)->getJson($endpoint)->assertStatus(403);
            }
        }
    }
}
