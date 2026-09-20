<?php

namespace Tests\Feature;

use App\Models\AdminPasswordReset;
use App\Models\RecoveryEmailVerification;
use App\Models\User;
use App\Notifications\AdminPasswordResetNotification;
use App\Notifications\AdminRecoveryEmailVerificationNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createUserWithRole(string $roleName, array $attributes = []): User
    {
        $uniqueEmail = 'user_' . Str::random(8) . '@hrms.local';
        $user = User::factory()->create(array_merge([
            'email' => $uniqueEmail,
            'password' => Hash::make('Password123!'),
        ], $attributes));
        $user->assignRole($roleName);
        return $user;
    }

    // =========================================================================
    // 1. RECOVERY EMAIL SETUP TESTS
    // =========================================================================

    public function test_super_admin_can_request_recovery_email_setup(): void
    {
        Notification::fake();
        $admin = $this->createUserWithRole('Super Admin');

        $response = $this->actingAs($admin)->postJson('/api/admin/recovery-email/setup', [
            'recovery_email' => 'recovery.super@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Verification link sent to recovery email. Please check your inbox to confirm.']);

        // Recovery email on user should NOT be updated yet
        $this->assertNull($admin->fresh()->recovery_email);
        $this->assertNull($admin->fresh()->recovery_email_verified_at);

        // Verification record created
        $this->assertDatabaseHas('recovery_email_verifications', [
            'user_id' => $admin->id,
            'recovery_email' => 'recovery.super@example.com',
            'verified_at' => null,
        ]);

        // Token hash is SHA-256 (64 characters)
        $record = RecoveryEmailVerification::where('user_id', $admin->id)->first();
        $this->assertEquals(64, strlen($record->token_hash));

        Notification::assertSentOnDemand(
            AdminRecoveryEmailVerificationNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'recovery.super@example.com';
            }
        );
    }

    public function test_hr_admin_can_request_recovery_email_setup(): void
    {
        Notification::fake();
        $hrAdmin = $this->createUserWithRole('HR Admin');

        $response = $this->actingAs($hrAdmin)->postJson('/api/admin/recovery-email/setup', [
            'recovery_email' => 'recovery.hr@example.com',
        ]);

        $response->assertStatus(200);

        Notification::assertSentOnDemand(
            AdminRecoveryEmailVerificationNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'recovery.hr@example.com';
            }
        );
    }

    public function test_employee_cannot_setup_recovery_email(): void
    {
        $employee = $this->createUserWithRole('Employee');

        $response = $this->actingAs($employee)->postJson('/api/admin/recovery-email/setup', [
            'recovery_email' => 'employee.recovery@example.com',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_setup_recovery_email(): void
    {
        $response = $this->postJson('/api/admin/recovery-email/setup', [
            'recovery_email' => 'unauth@example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_setup_recovery_email_validation_failure(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $response = $this->actingAs($admin)->postJson('/api/admin/recovery-email/setup', [
            'recovery_email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['recovery_email']);
    }

    // =========================================================================
    // 2. RECOVERY EMAIL VERIFICATION TESTS
    // =========================================================================

    public function test_valid_verification_token_verifies_recovery_email(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $rawToken = Str::random(64);

        $verification = RecoveryEmailVerification::create([
            'user_id' => $admin->id,
            'recovery_email' => 'verified.recovery@example.com',
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->postJson('/api/admin/recovery-email/verify', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Recovery email verified successfully.']);

        $this->assertEquals('verified.recovery@example.com', $admin->fresh()->recovery_email);
        $this->assertNotNull($admin->fresh()->recovery_email_verified_at);
        $this->assertNotNull($verification->fresh()->verified_at);
    }

    public function test_verification_token_cannot_be_reused(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $rawToken = Str::random(64);

        RecoveryEmailVerification::create([
            'user_id' => $admin->id,
            'recovery_email' => 'verified.reuse@example.com',
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHours(24),
        ]);

        // First attempt succeeds
        $this->postJson('/api/admin/recovery-email/verify', ['token' => $rawToken])
            ->assertStatus(200);

        // Second attempt fails
        $this->postJson('/api/admin/recovery-email/verify', ['token' => $rawToken])
            ->assertStatus(400);
    }

    public function test_invalid_verification_token_fails(): void
    {
        $response = $this->postJson('/api/admin/recovery-email/verify', [
            'token' => 'invalid_token_string',
        ]);

        $response->assertStatus(400);
    }

    public function test_expired_verification_token_fails(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $rawToken = Str::random(64);

        RecoveryEmailVerification::create([
            'user_id' => $admin->id,
            'recovery_email' => 'expired.recovery@example.com',
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->postJson('/api/admin/recovery-email/verify', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(400);
    }

    // =========================================================================
    // 3. RECOVERY STATUS TESTS
    // =========================================================================

    public function test_super_admin_and_hr_admin_can_view_recovery_status(): void
    {
        $admin = $this->createUserWithRole('Super Admin', [
            'recovery_email' => 'admin.personal@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/recovery-email/status');

        $response->assertStatus(200)
            ->assertJsonStructure(['recovery_email', 'is_verified', 'verified_at'])
            ->assertJson([
                'is_verified' => true,
            ]);

        // Ensure email is masked (contains *)
        $this->assertStringContainsString('*', $response->json('recovery_email'));
        $this->assertStringEndsWith('@example.com', $response->json('recovery_email'));
    }

    public function test_employee_cannot_view_recovery_status(): void
    {
        $employee = $this->createUserWithRole('Employee');

        $response = $this->actingAs($employee)->postJson('/api/admin/recovery-email/setup', []);
        $response = $this->actingAs($employee)->getJson('/api/admin/recovery-email/status');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_view_recovery_status(): void
    {
        $response = $this->getJson('/api/admin/recovery-email/status');

        $response->assertStatus(401);
    }

    // =========================================================================
    // 4. PASSWORD RECOVERY REQUEST TESTS & ACCOUNT ENUMERATION PREVENTION
    // =========================================================================

    public function test_eligible_super_admin_can_request_password_recovery(): void
    {
        Notification::fake();
        $admin = $this->createUserWithRole('Super Admin', [
            'recovery_email' => 'super.recovery@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/request', [
            'email' => $admin->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If an eligible account with a verified recovery email exists, password reset instructions have been sent.']);

        $this->assertDatabaseHas('admin_password_resets', [
            'user_id' => $admin->id,
            'used_at' => null,
        ]);

        Notification::assertSentOnDemand(
            AdminPasswordResetNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'super.recovery@example.com';
            }
        );
    }

    public function test_eligible_hr_admin_can_request_password_recovery(): void
    {
        Notification::fake();
        $hrAdmin = $this->createUserWithRole('HR Admin', [
            'recovery_email' => 'hr.recovery@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/request', [
            'email' => $hrAdmin->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If an eligible account with a verified recovery email exists, password reset instructions have been sent.']);

        $this->assertDatabaseHas('admin_password_resets', [
            'user_id' => $hrAdmin->id,
        ]);
    }

    public function test_employee_requesting_recovery_receives_generic_response_without_creating_token(): void
    {
        Notification::fake();
        $employee = $this->createUserWithRole('Employee', [
            'recovery_email' => 'employee.rec@example.com',
            'recovery_email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/request', [
            'email' => $employee->email,
        ]);

        // Must return identical generic response
        $response->assertStatus(200)
            ->assertJson(['message' => 'If an eligible account with a verified recovery email exists, password reset instructions have been sent.']);

        $this->assertDatabaseMissing('admin_password_resets', [
            'user_id' => $employee->id,
        ]);

        Notification::assertNothingSent();
    }

    public function test_nonexistent_email_receives_generic_response(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/admin/password-recovery/request', [
            'email' => 'nonexistent_' . Str::random(5) . '@hrms.local',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If an eligible account with a verified recovery email exists, password reset instructions have been sent.']);

        Notification::assertNothingSent();
    }

    public function test_unverified_recovery_email_receives_generic_response_without_token(): void
    {
        Notification::fake();
        $admin = $this->createUserWithRole('Super Admin', [
            'recovery_email' => 'unverified@example.com',
            'recovery_email_verified_at' => null, // Unverified!
        ]);

        $response = $this->postJson('/api/admin/password-recovery/request', [
            'email' => $admin->email,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'If an eligible account with a verified recovery email exists, password reset instructions have been sent.']);

        $this->assertDatabaseMissing('admin_password_resets', [
            'user_id' => $admin->id,
        ]);

        Notification::assertNothingSent();
    }

    // =========================================================================
    // 5. RESET TOKEN VERIFICATION TESTS
    // =========================================================================

    public function test_valid_reset_token_verification(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $rawToken = Str::random(64);

        AdminPasswordReset::create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/verify-token', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(200)
            ->assertJson(['valid' => true]);
    }

    public function test_invalid_reset_token_verification_fails(): void
    {
        $response = $this->postJson('/api/admin/password-recovery/verify-token', [
            'token' => 'invalid_token_string_999',
        ]);

        $response->assertStatus(400)
            ->assertJson(['valid' => false]);
    }

    public function test_expired_reset_token_verification_fails(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $rawToken = Str::random(64);

        AdminPasswordReset::create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/verify-token', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(400)
            ->assertJson(['valid' => false]);
    }

    public function test_used_reset_token_verification_fails(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $rawToken = Str::random(64);

        AdminPasswordReset::create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
            'used_at' => now(),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/verify-token', [
            'token' => $rawToken,
        ]);

        $response->assertStatus(400)
            ->assertJson(['valid' => false]);
    }

    // =========================================================================
    // 6. PASSWORD RESET TESTS
    // =========================================================================

    public function test_successful_password_reset(): void
    {
        $admin = $this->createUserWithRole('Super Admin', [
            'password' => Hash::make('OldPassword123!'),
        ]);
        $rawToken = Str::random(64);

        $reset = AdminPasswordReset::create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/admin/password-recovery/reset', [
            'token' => $rawToken,
            'password' => 'NewSecretPassword123!',
            'password_confirmation' => 'NewSecretPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password updated successfully. All existing sessions have been terminated. Please log in with your new password.']);

        // Assert reset token marked used
        $this->assertNotNull($reset->fresh()->used_at);

        // Assert password updated and old password fails
        $this->assertTrue(Hash::check('NewSecretPassword123!', $admin->fresh()->password));
        $this->assertFalse(Hash::check('OldPassword123!', $admin->fresh()->password));

        // Token cannot be reused
        $this->postJson('/api/admin/password-recovery/reset', [
            'token' => $rawToken,
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ])->assertStatus(400);
    }

    // =========================================================================
    // 7. SANCTUM TOKEN REVOCATION TEST
    // =========================================================================

    public function test_password_reset_revokes_all_sanctum_tokens(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $sanctumToken = $admin->createToken('active_bearer_token')->plainTextToken;

        // Perform password reset via public reset endpoint
        $rawToken = Str::random(64);
        AdminPasswordReset::create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/admin/password-recovery/reset', [
            'token' => $rawToken,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ])->assertStatus(200);

        // Ensure in-memory test guard authentication is reset so Sanctum bearer header is tested authentically
        $this->app['auth']->forgetGuards();

        // Attempt API call with previously active Sanctum token -> Must be revoked (401)
        $this->withHeader('Authorization', 'Bearer ' . $sanctumToken)
            ->getJson('/api/user')
            ->assertStatus(401);
    }

    // =========================================================================
    // 8. PASSWORD VALIDATION TESTS
    // =========================================================================

    public function test_password_reset_validation_errors(): void
    {
        $response = $this->postJson('/api/admin/password-recovery/reset', [
            'token' => 'some_token',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // =========================================================================
    // 9. NORMAL LOGIN REGRESSION TEST
    // =========================================================================

    public function test_normal_login_works_for_admin_and_employee(): void
    {
        $admin = $this->createUserWithRole('Super Admin', [
            'password' => Hash::make('AdminPass123!'),
        ]);

        $employee = $this->createUserWithRole('Employee', [
            'password' => Hash::make('EmployeePass123!'),
        ]);

        // Admin login
        $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'AdminPass123!',
        ])->assertStatus(200)
            ->assertJsonStructure(['access_token']);

        // Employee login
        $this->postJson('/api/login', [
            'email' => $employee->email,
            'password' => 'EmployeePass123!',
        ])->assertStatus(200)
            ->assertJsonStructure(['access_token']);
    }
}
