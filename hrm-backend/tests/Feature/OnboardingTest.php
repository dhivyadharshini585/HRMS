<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\OfferLetter;
use App\Models\Onboarding;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function getSuperAdmin()
    {
        return User::where('email', 'superadmin@hrms.local')->first();
    }

    public function test_authorized_hr_can_start_onboarding()
    {
        $admin = $this->getSuperAdmin();
        $candidate = Candidate::factory()->create();
        $offer = OfferLetter::factory()->create([
            'candidate_id' => $candidate->id,
            'offer_status' => 'Accepted'
        ]);

        $response = $this->actingAs($admin)->postJson('/api/onboarding', [
            'offer_letter_id' => $offer->id
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Onboarding started successfully.');
        
        $this->assertDatabaseHas('onboardings', [
            'offer_letter_id' => $offer->id,
            'status' => 'In Progress'
        ]);
        
        $this->assertDatabaseHas('onboarding_checklist_items', [
            'onboarding_id' => $response->json('data.id'),
            'item_type' => 'ID Proof'
        ]);
    }

    public function test_unauthorized_roles_receive_403()
    {
        $user = User::factory()->create(); // No roles

        $response = $this->actingAs($user)->postJson('/api/onboarding', [
            'offer_letter_id' => 1
        ]);

        $response->assertStatus(403);
    }

    public function test_draft_offer_cannot_create_onboarding()
    {
        $admin = $this->getSuperAdmin();
        $offer = OfferLetter::factory()->create(['offer_status' => 'Draft']);

        $response = $this->actingAs($admin)->postJson('/api/onboarding', [
            'offer_letter_id' => $offer->id
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Onboarding can only be started for an Accepted offer. Current status is Draft.');
    }

    public function test_employee_creation_blocked_until_required_checklist_verified()
    {
        $admin = $this->getSuperAdmin();
        $candidate = Candidate::factory()->create();
        $offer = OfferLetter::factory()->create([
            'candidate_id' => $candidate->id,
            'offer_status' => 'Accepted'
        ]);

        $startResponse = $this->actingAs($admin)->postJson('/api/onboarding', [
            'offer_letter_id' => $offer->id
        ]);
        
        $onboardingId = $startResponse->json('data.id');

        $response = $this->actingAs($admin)->postJson("/api/onboarding/{$onboardingId}/create-employee");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'All required documents must be Verified before creating the employee.');
    }

    public function test_completion_blocked_until_employee_created()
    {
        $admin = $this->getSuperAdmin();
        $offer = OfferLetter::factory()->create(['offer_status' => 'Accepted']);

        $startResponse = $this->actingAs($admin)->postJson('/api/onboarding', [
            'offer_letter_id' => $offer->id
        ]);
        
        $onboardingId = $startResponse->json('data.id');

        // Mark all checklist items as verified manually for the test
        $onboarding = Onboarding::find($onboardingId);
        $onboarding->checklistItems()->update(['status' => 'Verified']);

        $response = $this->actingAs($admin)->putJson("/api/onboarding/{$onboardingId}/complete");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Employee record must be created before completing onboarding.');
    }
}
