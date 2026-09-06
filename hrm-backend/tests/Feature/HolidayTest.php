<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $managerUser;
    protected User $employeeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();
    }

    /** @test */
    public function test_9_authorized_hr_admin_can_list_holidays()
    {
        Holiday::create(['name' => 'New Year', 'holiday_date' => '2026-01-01', 'holiday_type' => 'National']);

        $response = $this->actingAs($this->hrAdmin)->getJson('/api/holidays');
        $response->assertStatus(200)->assertJsonStructure(['data']);
    }

    /** @test */
    public function test_10_authorized_hr_admin_can_create_a_holiday()
    {
        $payload = [
            'name' => 'Republic Day',
            'holiday_date' => '2026-01-26',
            'holiday_type' => 'National',
            'description' => 'National Holiday',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/holidays', $payload);
        $response->assertStatus(201)->assertJsonFragment(['name' => 'Republic Day', 'holiday_type' => 'National']);

        $this->assertDatabaseHas('holidays', ['name' => 'Republic Day', 'holiday_type' => 'National']);
    }

    /** @test */
    public function test_11_invalid_holiday_type_is_rejected()
    {
        $payload = [
            'name' => 'Invalid Type Day',
            'holiday_date' => '2026-03-01',
            'holiday_type' => 'ArbitraryType',
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/holidays', $payload);
        $response->assertStatus(422)->assertJsonValidationErrors('holiday_type');
    }

    /** @test */
    public function test_12_authorized_hr_admin_can_update_a_holiday()
    {
        $holiday = Holiday::create(['name' => 'Labor Day', 'holiday_date' => '2026-05-01', 'holiday_type' => 'Company']);

        $updatePayload = [
            'name' => 'International Labor Day',
            'holiday_date' => '2026-05-01',
            'holiday_type' => 'National',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->hrAdmin)->putJson("/api/holidays/{$holiday->id}", $updatePayload);
        $response->assertStatus(200)->assertJsonFragment(['name' => 'International Labor Day', 'holiday_type' => 'National']);
    }

    /** @test */
    public function test_13_authorized_hr_admin_can_delete_a_holiday()
    {
        $holiday = Holiday::create(['name' => 'Temp Holiday', 'holiday_date' => '2026-08-15', 'holiday_type' => 'Regional']);

        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/holidays/{$holiday->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    /** @test */
    public function test_14_employee_can_view_holidays()
    {
        Holiday::create(['name' => 'Independence Day', 'holiday_date' => '2026-08-15', 'holiday_type' => 'National']);

        $response = $this->actingAs($this->employeeUser)->getJson('/api/holidays');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function test_15_employee_cannot_create_update_or_delete_holidays()
    {
        $holiday = Holiday::create(['name' => 'Gandhi Jayanti', 'holiday_date' => '2026-10-02', 'holiday_type' => 'National']);

        // POST (Create) -> 403
        $responsePost = $this->actingAs($this->employeeUser)->postJson('/api/holidays', ['name' => 'Fake', 'holiday_date' => '2026-12-25']);
        $responsePost->assertStatus(403);

        // PUT (Update) -> 403
        $responsePut = $this->actingAs($this->employeeUser)->putJson("/api/holidays/{$holiday->id}", ['name' => 'Fake', 'holiday_date' => '2026-10-02']);
        $responsePut->assertStatus(403);

        // DELETE -> 403
        $responseDelete = $this->actingAs($this->employeeUser)->deleteJson("/api/holidays/{$holiday->id}");
        $responseDelete->assertStatus(403);
    }

    /** @test */
    public function test_16_duplicate_holiday_date_is_prevented()
    {
        Holiday::create(['name' => 'Christmas', 'holiday_date' => '2026-12-25', 'holiday_type' => 'National']);

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/holidays', [
            'name' => 'Xmas Eve',
            'holiday_date' => '2026-12-25',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('holiday_date');
    }

    /** @test */
    public function test_17_holiday_changes_create_audit_logs()
    {
        // Create Holiday
        $response = $this->actingAs($this->superAdmin)->postJson('/api/holidays', [
            'name' => 'Audit Holiday',
            'holiday_date' => '2026-11-01',
            'holiday_type' => 'National',
        ]);

        $holidayId = $response->json('id');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'holiday.created',
            'entity_type' => Holiday::class,
            'entity_id' => $holidayId,
        ]);

        // Delete Holiday
        $this->actingAs($this->superAdmin)->deleteJson("/api/holidays/{$holidayId}");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'holiday.deleted',
            'entity_type' => Holiday::class,
            'entity_id' => $holidayId,
        ]);
    }

    /** @test */
    public function test_18_optional_holiday_type_creation_and_update()
    {
        // 1. Create Optional Holiday
        $payload = [
            'name' => 'Optional Festival Holiday',
            'holiday_date' => '2026-11-15',
            'holiday_type' => 'Optional',
            'description' => 'Employee optional floating holiday',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->hrAdmin)->postJson('/api/holidays', $payload);
        $response->assertStatus(201)->assertJsonFragment(['name' => 'Optional Festival Holiday', 'holiday_type' => 'Optional']);

        $holidayId = $response->json('id');
        $this->assertDatabaseHas('holidays', ['id' => $holidayId, 'holiday_type' => 'Optional']);

        // 2. Update to Regional then back to Optional
        $updatePayload = [
            'name' => 'Optional Festival Holiday Updated',
            'holiday_date' => '2026-11-15',
            'holiday_type' => 'Optional',
            'is_active' => true,
        ];

        $updateResponse = $this->actingAs($this->hrAdmin)->putJson("/api/holidays/{$holidayId}", $updatePayload);
        $updateResponse->assertStatus(200)->assertJsonFragment(['name' => 'Optional Festival Holiday Updated', 'holiday_type' => 'Optional']);

        // 3. Confirm API returns Optional correctly in list
        $listResponse = $this->actingAs($this->employeeUser)->getJson('/api/holidays');
        $listResponse->assertStatus(200);
        $this->assertTrue(collect($listResponse->json('data'))->contains('holiday_type', 'Optional'));
    }
}
