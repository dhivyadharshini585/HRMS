<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\JobOpening;
use App\Models\OfferLetter;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfferLetterTest extends TestCase
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
    protected JobOpening $jobOpening;
    protected Candidate $hiredCandidate;
    protected Candidate $screeningCandidate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->hrExecutive = User::where('email', 'hrexecutive@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();
        $this->payrollUser = User::where('email', 'payroll@hrms.local')->first();

        $this->dept = Department::create([
            'name' => 'Engineering',
            'description' => 'Software engineering',
            'status' => 'Active',
        ]);

        $this->desig = Designation::create([
            'title' => 'Software Engineer',
            'department_id' => $this->dept->id,
            'status' => 'Active',
        ]);

        $this->jobOpening = JobOpening::create([
            'title' => 'Senior Backend Developer',
            'job_code' => 'JOB-2026-001',
            'department_id' => $this->dept->id,
            'location' => 'Austin, TX',
            'employment_type' => 'Full Time',
            'openings_count' => 2,
            'description' => 'Test backend developer opening',
            'status' => 'Open',
            'posted_by' => $this->hrAdmin->id,
        ]);

        // Hired candidate eligible for offer letter
        $this->hiredCandidate = Candidate::create([
            'candidate_code' => 'CAND-2026-001',
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice.smith@example.com',
            'phone' => '+15551234567',
            'job_opening_id' => $this->jobOpening->id,
            'status' => 'Hired',
            'created_by' => $this->hrAdmin->id,
        ]);

        // Screening candidate ineligible for offer letter
        $this->screeningCandidate = Candidate::create([
            'candidate_code' => 'CAND-2026-002',
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'email' => 'bob.jones@example.com',
            'phone' => '+15559876543',
            'job_opening_id' => $this->jobOpening->id,
            'status' => 'Screening',
            'created_by' => $this->hrAdmin->id,
        ]);
    }

    protected function validOfferData(array $overrides = []): array
    {
        return array_merge([
            'candidate_id' => $this->hiredCandidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'department_id' => $this->dept->id,
            'offer_date' => now()->toDateString(),
            'joining_date' => now()->addWeeks(2)->toDateString(),
            'designation' => 'Senior Backend Developer',
            'employment_type' => 'Full-time',
            'work_location' => 'Austin, TX',
            'salary_amount' => 125000.00,
            'salary_currency' => 'USD',
            'salary_frequency' => 'Annual',
            'probation_period_months' => 3,
            'notice_period_days' => 30,
            'benefits' => 'Health insurance, 401k match, 20 days PTO',
            'terms_and_conditions' => 'Standard employment at-will agreement',
        ], $overrides);
    }

    public function test_hr_admin_can_list_offer_letters(): void
    {
        OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->getJson('/api/offer-letters');

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_hr_admin_can_create_offer_for_hired_candidate(): void
    {
        $data = $this->validOfferData();

        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $data);

        $response->assertCreated()
            ->assertJsonPath('data.designation', 'Senior Backend Developer')
            ->assertJsonPath('data.offer_status', 'Draft');

        $this->assertDatabaseHas('offer_letters', [
            'candidate_id' => $this->hiredCandidate->id,
            'job_opening_id' => $this->jobOpening->id,
            'offer_status' => 'Draft',
        ]);

        $offer = OfferLetter::first();
        $this->assertNotNull($offer->offer_code);
        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{6}$/', $offer->offer_code);
        $this->assertTrue(Storage::disk('local')->exists($offer->document_path));
    }

    public function test_non_hired_candidate_cannot_receive_offer_letter(): void
    {
        $data = $this->validOfferData([
            'candidate_id' => $this->screeningCandidate->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['candidate_id']);

        $this->assertDatabaseMissing('offer_letters', [
            'candidate_id' => $this->screeningCandidate->id,
        ]);
    }

    public function test_candidate_job_opening_mismatch_is_rejected(): void
    {
        $otherJob = JobOpening::create([
            'title' => 'Frontend Developer',
            'job_code' => 'JOB-2026-002',
            'department_id' => $this->dept->id,
            'location' => 'Remote',
            'employment_type' => 'Full Time',
            'openings_count' => 1,
            'description' => 'Test frontend developer opening',
            'status' => 'Open',
            'posted_by' => $this->hrAdmin->id,
        ]);

        $data = $this->validOfferData([
            'job_opening_id' => $otherJob->id,
        ]);

        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['job_opening_id']);
    }

    public function test_offer_code_is_generated_server_side_and_unique(): void
    {
        $response1 = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData());
        $response1->assertCreated();

        $code1 = $response1->json('data.offer_code');

        // Create second hired candidate
        $candidate2 = Candidate::create([
            'candidate_code' => 'CAND-2026-003',
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'email' => 'charlie@example.com',
            'phone' => '+15554443322',
            'job_opening_id' => $this->jobOpening->id,
            'status' => 'Hired',
            'created_by' => $this->hrAdmin->id,
        ]);

        $response2 = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData([
                'candidate_id' => $candidate2->id,
                'offer_code' => 'CLIENT_CODE_IGNORED',
            ]));
        $response2->assertCreated();

        $code2 = $response2->json('data.offer_code');

        $this->assertNotEquals('CLIENT_CODE_IGNORED', $code2);
        $this->assertNotEquals($code1, $code2);
        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{6}$/', $code2);
    }

    public function test_invalid_salary_amount_is_rejected(): void
    {
        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData([
                'salary_amount' => 0,
            ]));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['salary_amount']);

        $responseNeg = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData([
                'salary_amount' => -1000,
            ]));
        $responseNeg->assertStatus(422)
            ->assertJsonValidationErrors(['salary_amount']);
    }

    public function test_joining_date_before_offer_date_is_rejected(): void
    {
        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData([
                'offer_date' => '2026-09-10',
                'joining_date' => '2026-09-01',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['joining_date']);
    }

    public function test_invalid_employment_type_is_rejected(): void
    {
        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData([
                'employment_type' => 'FreelanceGig',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employment_type']);
    }

    public function test_duplicate_active_offer_for_same_candidate_is_rejected(): void
    {
        // First active offer (Draft)
        $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData())
            ->assertCreated();

        // Attempt second offer for same candidate
        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['candidate_id']);
    }

    public function test_historical_rejected_or_withdrawn_offer_does_not_block_new_offer(): void
    {
        // Create an offer in Rejected status
        OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Rejected',
            'created_by' => $this->hrAdmin->id,
        ]));

        // New offer for same candidate should succeed
        $response = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData());

        $response->assertCreated();
    }

    public function test_draft_offer_can_be_updated(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->putJson("/api/offer-letters/{$offer->id}", [
                'designation' => 'Lead Backend Architect',
                'salary_amount' => 140000.00,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.designation', 'Lead Backend Architect');

        $this->assertDatabaseHas('offer_letters', [
            'id' => $offer->id,
            'designation' => 'Lead Backend Architect',
        ]);
    }

    public function test_sent_offer_cannot_be_updated(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->putJson("/api/offer-letters/{$offer->id}", [
                'designation' => 'Updated Title',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_status']);
    }

    public function test_draft_offer_can_be_sent(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/send");

        $response->assertOk()
            ->assertJsonPath('data.offer_status', 'Sent');

        $offer->refresh();
        $this->assertEquals('Sent', $offer->offer_status);
        $this->assertNotNull($offer->sent_at);
    }

    public function test_draft_offer_cannot_be_accepted_directly(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/accept", [
                'response_remarks' => 'Direct acceptance attempt',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_status']);
    }

    public function test_draft_offer_cannot_be_rejected_directly(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/reject", [
                'response_remarks' => 'Direct rejection attempt',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_status']);
    }

    public function test_draft_offer_cannot_be_withdrawn_directly(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/withdraw");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['offer_status']);
    }

    public function test_sent_offer_can_be_accepted(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now()->subDay(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/accept", [
                'response_remarks' => 'Candidate accepted offer gladly',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.offer_status', 'Accepted');

        $offer->refresh();
        $this->assertEquals('Accepted', $offer->offer_status);
        $this->assertNotNull($offer->responded_at);
        $this->assertEquals('Candidate accepted offer gladly', $offer->response_remarks);
    }

    public function test_sent_offer_can_be_rejected_with_required_remarks(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now()->subDay(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/reject", [
                'response_remarks' => 'Candidate accepted another counter-offer',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.offer_status', 'Rejected');

        $offer->refresh();
        $this->assertEquals('Rejected', $offer->offer_status);
        $this->assertNotNull($offer->responded_at);
    }

    public function test_sent_offer_rejection_without_remarks_is_blocked(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now()->subDay(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/reject", [
                'response_remarks' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['response_remarks']);
    }

    public function test_sent_offer_can_be_withdrawn(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now()->subDay(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/withdraw", [
                'response_remarks' => 'Position put on administrative hold',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.offer_status', 'Withdrawn');

        $offer->refresh();
        $this->assertEquals('Withdrawn', $offer->offer_status);
    }

    public function test_sent_offer_can_be_expired(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now()->subWeeks(2),
            'created_by' => $this->hrAdmin->id,
        ]));

        $response = $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/expire", [
                'response_remarks' => 'Offer deadline lapsed without response',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.offer_status', 'Expired');

        $offer->refresh();
        $this->assertEquals('Expired', $offer->offer_status);
    }

    public function test_accepted_offer_cannot_transition_again(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Accepted',
            'responded_at' => now(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/reject", ['response_remarks' => 'test'])
            ->assertStatus(422);

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/withdraw")
            ->assertStatus(422);

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/expire")
            ->assertStatus(422);
    }

    public function test_rejected_offer_cannot_transition_again(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Rejected',
            'responded_at' => now(),
            'response_remarks' => 'Rejected',
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/accept")
            ->assertStatus(422);
    }

    public function test_withdrawn_offer_cannot_transition_again(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Withdrawn',
            'responded_at' => now(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/accept")
            ->assertStatus(422);
    }

    public function test_candidate_status_remains_hired_after_offer_creation_and_acceptance(): void
    {
        $this->assertEquals('Hired', $this->hiredCandidate->status);

        // Create offer
        $res = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData());
        $res->assertCreated();
        $offerId = $res->json('data.id');

        $this->hiredCandidate->refresh();
        $this->assertEquals('Hired', $this->hiredCandidate->status);

        // Send offer
        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offerId}/send")
            ->assertOk();

        $this->hiredCandidate->refresh();
        $this->assertEquals('Hired', $this->hiredCandidate->status);

        // Accept offer
        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offerId}/accept")
            ->assertOk();

        $this->hiredCandidate->refresh();
        $this->assertEquals('Hired', $this->hiredCandidate->status);
    }

    public function test_no_onboarding_record_is_created_on_offer_acceptance(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Sent',
            'sent_at' => now(),
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offer->id}/accept", [
                'response_remarks' => 'Accepted',
            ])
            ->assertOk();

        // Ensure no onboarding tables or models were affected
        $this->assertDatabaseMissing('onboardings', [
            'offer_letter_id' => $offer->id,
        ]);
    }

    public function test_hr_executive_can_create_update_send_and_download(): void
    {
        // HR Executive can create
        $res = $this->actingAs($this->hrExecutive)
            ->postJson('/api/offer-letters', $this->validOfferData());
        $res->assertCreated();
        $offerId = $res->json('data.id');

        // HR Executive can update draft
        $this->actingAs($this->hrExecutive)
            ->putJson("/api/offer-letters/{$offerId}", [
                'salary_amount' => 130000.00,
            ])
            ->assertOk();

        // HR Executive can send
        $this->actingAs($this->hrExecutive)
            ->patchJson("/api/offer-letters/{$offerId}/send")
            ->assertOk();

        // HR Executive can respond (accept)
        $this->actingAs($this->hrExecutive)
            ->patchJson("/api/offer-letters/{$offerId}/accept", ['response_remarks' => 'Accepted'])
            ->assertOk();

        // HR Executive can download
        $this->actingAs($this->hrExecutive)
            ->getJson("/api/offer-letters/{$offerId}/download")
            ->assertOk();
    }

    public function test_hr_executive_cannot_delete_offer_letter(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrExecutive->id,
        ]));

        $response = $this->actingAs($this->hrExecutive)
            ->deleteJson("/api/offer-letters/{$offer->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('offer_letters', ['id' => $offer->id]);
    }

    public function test_manager_cannot_access_offer_letters(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->managerUser)
            ->getJson('/api/offer-letters')
            ->assertStatus(403);

        $this->actingAs($this->managerUser)
            ->postJson('/api/offer-letters', $this->validOfferData())
            ->assertStatus(403);

        $this->actingAs($this->managerUser)
            ->getJson("/api/offer-letters/{$offer->id}")
            ->assertStatus(403);

        $this->actingAs($this->managerUser)
            ->deleteJson("/api/offer-letters/{$offer->id}")
            ->assertStatus(403);
    }

    public function test_employee_cannot_access_offer_letters(): void
    {
        $this->actingAs($this->employeeUser)
            ->getJson('/api/offer-letters')
            ->assertStatus(403);

        $this->actingAs($this->employeeUser)
            ->postJson('/api/offer-letters', $this->validOfferData())
            ->assertStatus(403);
    }

    public function test_finance_payroll_admin_cannot_access_offer_letters(): void
    {
        $this->actingAs($this->payrollUser)
            ->getJson('/api/offer-letters')
            ->assertStatus(403);

        $this->actingAs($this->payrollUser)
            ->postJson('/api/offer-letters', $this->validOfferData())
            ->assertStatus(403);
    }

    public function test_unauthorized_download_blocked(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->employeeUser)
            ->getJson("/api/offer-letters/{$offer->id}/download")
            ->assertStatus(403);

        $this->actingAs($this->managerUser)
            ->getJson("/api/offer-letters/{$offer->id}/download")
            ->assertStatus(403);
    }

    public function test_offer_letter_pdf_document_generation_and_private_storage(): void
    {
        $res = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData());

        $res->assertCreated();
        $offer = OfferLetter::first();

        $this->assertNotEmpty($offer->document_path);
        $this->assertStringStartsWith('private/offer-letters/', $offer->document_path);
        $this->assertTrue(Storage::disk('local')->exists($offer->document_path));

        // Test download
        $downloadRes = $this->actingAs($this->hrAdmin)
            ->getJson("/api/offer-letters/{$offer->id}/download");

        $downloadRes->assertOk();
        $downloadRes->assertHeader('content-type', 'application/pdf');
    }

    public function test_audit_logs_created_for_all_offer_letter_lifecycle_events(): void
    {
        $res = $this->actingAs($this->hrAdmin)
            ->postJson('/api/offer-letters', $this->validOfferData());
        $res->assertCreated();
        $offerId = $res->json('data.id');

        $this->actingAs($this->hrAdmin)
            ->putJson("/api/offer-letters/{$offerId}", ['salary_amount' => 135000.00])
            ->assertOk();

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offerId}/send")
            ->assertOk();

        $this->actingAs($this->hrAdmin)
            ->patchJson("/api/offer-letters/{$offerId}/accept")
            ->assertOk();

        $auditActions = AuditLog::where('entity_id', $offerId)->pluck('action')->toArray();

        $this->assertContains('offer_letter.created', $auditActions);
        $this->assertContains('offer_letter.updated', $auditActions);
        $this->assertContains('offer_letter.sent', $auditActions);
        $this->assertContains('offer_letter.accepted', $auditActions);
    }

    public function test_soft_delete_and_restore_behavior(): void
    {
        $offer = OfferLetter::create(array_merge($this->validOfferData(), [
            'offer_code' => 'OFF-2026-000001',
            'offer_status' => 'Draft',
            'created_by' => $this->hrAdmin->id,
        ]));

        $this->actingAs($this->hrAdmin)
            ->deleteJson("/api/offer-letters/{$offer->id}")
            ->assertOk();

        $this->assertSoftDeleted('offer_letters', ['id' => $offer->id]);

        // Restoring model
        $offer->restore();
        $this->assertNotSoftDeleted('offer_letters', ['id' => $offer->id]);
    }
}
