<?php

namespace App\Services;

use App\Models\AdminPasswordReset;
use App\Models\RecoveryEmailVerification;
use App\Models\User;
use App\Notifications\AdminPasswordResetNotification;
use App\Notifications\AdminRecoveryEmailVerificationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminRecoveryService
{
    /**
     * Eligible admin roles for password recovery operations.
     */
    protected const ELIGIBLE_ROLES = ['Super Admin', 'HR Admin'];

    /**
     * Request a recovery email configuration and send a verification email.
     */
    public function setupRecoveryEmail(User $user, string $recoveryEmail): void
    {
        if (!$user->hasAnyRole(self::ELIGIBLE_ROLES)) {
            throw new AccessDeniedHttpException('Only Super Admin and HR Admin users can configure a recovery email.');
        }

        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        // Create verification record (expires in 24 hours)
        RecoveryEmailVerification::create([
            'user_id' => $user->id,
            'recovery_email' => $recoveryEmail,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addHours(24),
            'verified_at' => null,
        ]);

        // Send verification notification to the requested recovery email
        Notification::route('mail', $recoveryEmail)->notify(
            new AdminRecoveryEmailVerificationNotification($rawToken)
        );

        AuditService::log('recovery_email_setup_requested', 'Admin recovery email setup requested', [
            'user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    /**
     * Verify a recovery email token and update the user's active recovery email.
     */
    public function verifyRecoveryEmail(string $rawToken): bool
    {
        $tokenHash = hash('sha256', $rawToken);

        $verificationRecord = RecoveryEmailVerification::where('token_hash', $tokenHash)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$verificationRecord) {
            return false;
        }

        DB::transaction(function () use ($verificationRecord) {
            $user = $verificationRecord->user;

            $user->recovery_email = $verificationRecord->recovery_email;
            $user->recovery_email_verified_at = now();
            $user->save();

            $verificationRecord->verified_at = now();
            $verificationRecord->save();

            AuditService::log('recovery_email_verified', 'Admin recovery email verified successfully', [
                'user_id' => $user->id,
                'entity_type' => User::class,
                'entity_id' => $user->id,
            ]);
        });

        return true;
    }

    /**
     * Get the current recovery email status for an administrator.
     */
    public function getRecoveryStatus(User $user): array
    {
        if (!$user->hasAnyRole(self::ELIGIBLE_ROLES)) {
            throw new AccessDeniedHttpException('Only Super Admin and HR Admin users can view recovery email status.');
        }

        $email = $user->recovery_email;
        $maskedEmail = null;

        if ($email) {
            $parts = explode('@', $email);
            if (count($parts) === 2) {
                $name = $parts[0];
                $domain = $parts[1];
                $length = strlen($name);
                if ($length <= 2) {
                    $maskedName = substr($name, 0, 1) . '*';
                } else {
                    $maskedName = substr($name, 0, 1) . str_repeat('*', max($length - 2, 1)) . substr($name, -1);
                }
                $maskedEmail = $maskedName . '@' . $domain;
            } else {
                $maskedEmail = '***@***';
            }
        }

        return [
            'recovery_email' => $maskedEmail,
            'is_verified' => !is_null($user->recovery_email_verified_at),
            'verified_at' => $user->recovery_email_verified_at?->toIso8601String(),
        ];
    }

    /**
     * Request a password reset.
     * Always handles ineligible requests silently to prevent account enumeration.
     */
    public function requestPasswordReset(?string $email, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        if (!$email) {
            return;
        }

        $user = User::where('email', $email)->first();

        // Check user existence, admin role eligibility, and verified recovery email presence
        if (!$user || !$user->hasAnyRole(self::ELIGIBLE_ROLES)) {
            return;
        }

        if (empty($user->recovery_email) || is_null($user->recovery_email_verified_at)) {
            return;
        }

        // Invalidate older unused reset tokens for this user
        AdminPasswordReset::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['expires_at' => now()]);

        $rawToken = Str::random(64);
        $tokenHash = hash('sha256', $rawToken);

        AdminPasswordReset::create([
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addMinutes(15),
            'used_at' => null,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Send reset notification to verified recovery email
        Notification::route('mail', $user->recovery_email)->notify(
            new AdminPasswordResetNotification($rawToken)
        );

        AuditService::log('admin_password_recovery_requested', 'Admin password recovery reset requested', [
            'user_id' => $user->id,
            'entity_type' => User::class,
            'entity_id' => $user->id,
        ]);
    }

    /**
     * Validate whether a password reset token is active, unused, and unexpired.
     */
    public function verifyResetToken(string $rawToken): bool
    {
        $tokenHash = hash('sha256', $rawToken);

        $resetRecord = AdminPasswordReset::where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        return !is_null($resetRecord);
    }

    /**
     * Execute password reset: update password, invalidate reset token, and revoke all Sanctum tokens.
     */
    public function resetPassword(string $rawToken, string $newPassword, ?string $ipAddress = null, ?string $userAgent = null): bool
    {
        $tokenHash = hash('sha256', $rawToken);

        $resetRecord = AdminPasswordReset::where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            return false;
        }

        DB::transaction(function () use ($resetRecord, $newPassword) {
            $user = $resetRecord->user;

            // Update user password
            $user->password = $newPassword; // Casts in User model automatically hash password attribute
            $user->save();

            // Revoke all existing Sanctum API bearer tokens
            $user->tokens()->delete();

            // Mark reset token as used
            $resetRecord->used_at = now();
            $resetRecord->save();

            AuditService::log('admin_password_reset_completed', 'Admin password reset completed successfully', [
                'user_id' => $user->id,
                'entity_type' => User::class,
                'entity_id' => $user->id,
            ]);
        });

        return true;
    }
}
