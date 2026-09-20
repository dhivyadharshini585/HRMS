<?php

namespace App\Http\Controllers;

use App\Services\AdminRecoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminRecoveryController extends Controller
{
    protected AdminRecoveryService $recoveryService;

    public function __construct(AdminRecoveryService $recoveryService)
    {
        $this->recoveryService = $recoveryService;
    }

    /**
     * Configure a recovery email address for the authenticated administrator.
     */
    public function setupRecoveryEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['Super Admin', 'HR Admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only Super Admin and HR Admin users can configure a recovery email.'
            ], 403);
        }

        $request->validate([
            'recovery_email' => 'required|string|email|max:255',
        ]);

        $this->recoveryService->setupRecoveryEmail($user, $request->recovery_email);

        return response()->json([
            'message' => 'Verification link sent to recovery email. Please check your inbox to confirm.'
        ]);
    }

    /**
     * Verify a recovery email address via verification token.
     */
    public function verifyRecoveryEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $verified = $this->recoveryService->verifyRecoveryEmail($request->token);

        if (!$verified) {
            return response()->json([
                'message' => 'Invalid, expired, or already verified token.'
            ], 400);
        }

        return response()->json([
            'message' => 'Recovery email verified successfully.'
        ]);
    }

    /**
     * Retrieve the recovery email configuration status for the authenticated admin.
     */
    public function getRecoveryEmailStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['Super Admin', 'HR Admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Only Super Admin and HR Admin users can view recovery status.'
            ], 403);
        }

        $status = $this->recoveryService->getRecoveryStatus($user);

        return response()->json($status);
    }

    /**
     * Request an admin password recovery reset link.
     * Always returns generic response to prevent account enumeration.
     */
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $this->recoveryService->requestPasswordReset(
            $request->email,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'message' => 'If an eligible account with a verified recovery email exists, password reset instructions have been sent.'
        ]);
    }

    /**
     * Validate whether a password reset token is active and valid.
     */
    public function verifyResetToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $valid = $this->recoveryService->verifyResetToken($request->token);

        if (!$valid) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired password reset token.'
            ], 400);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token is valid.'
        ]);
    }

    /**
     * Execute password reset for an admin account using a valid reset token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $success = $this->recoveryService->resetPassword(
            $request->token,
            $request->password,
            $request->ip(),
            $request->userAgent()
        );

        if (!$success) {
            return response()->json([
                'message' => 'Invalid, expired, or already used password reset token.'
            ], 400);
        }

        return response()->json([
            'message' => 'Password updated successfully. All existing sessions have been terminated. Please log in with your new password.'
        ]);
    }
}
