<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CandidateStatusPipelineService
{
    /**
     * Explicit recruitment status transition map.
     */
    public const ALLOWED_TRANSITIONS = [
        'New' => [
            'Screening',
        ],
        'Screening' => [
            'Shortlisted',
            'Rejected',
            'New', // Administrative reversal
        ],
        'Shortlisted' => [
            'Hired',
            'Rejected',
            'Screening', // Administrative reversal
        ],
        'Rejected' => [
            'Screening', // Administrative reopening/reconsideration
        ],
        'Hired' => [
            // Terminal state - no further recruitment transitions permitted
        ],
    ];

    /**
     * Get list of allowed next statuses for a given current status.
     */
    public static function getAllowedNextStatuses(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Verify if a transition between two statuses is allowed.
     */
    public static function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        return in_array($toStatus, $allowed, true);
    }

    /**
     * Validate prerequisites for marking a candidate as Hired.
     * Enforces:
     * 1. Candidate must have at least one interview with status = Completed.
     * 2. Candidate must have interview feedback associated with a completed interview.
     *
     * Returns null if valid, or an error string if invalid.
     */
    public static function validateHiredPrerequisites(Candidate $candidate): ?string
    {
        // 1. Must have at least one Completed interview
        $hasCompletedInterview = $candidate->interviews()
            ->where('status', 'Completed')
            ->exists();

        if (!$hasCompletedInterview) {
            return 'Cannot mark candidate as Hired: Candidate must have at least one completed interview.';
        }

        // 2. Must have at least one InterviewFeedback record associated with a Completed interview
        $hasCompletedWithFeedback = $candidate->interviews()
            ->where('status', 'Completed')
            ->whereHas('feedback')
            ->exists();

        if (!$hasCompletedWithFeedback) {
            return 'Cannot mark candidate as Hired: Candidate must have at least one completed interview with structured feedback.';
        }

        return null;
    }

    /**
     * Atomically transition a candidate to a new status.
     *
     * @throws ValidationException
     */
    public function transition(Candidate $candidate, string $toStatus, ?string $remarks = null, ?User $user = null): Candidate
    {
        $currentStatus = $candidate->status;

        // Disallow same-status transitions
        if ($currentStatus === $toStatus) {
            throw ValidationException::withMessages([
                'status' => ["Candidate is already in '{$toStatus}' status."],
            ]);
        }

        // Validate state machine transition rules
        if (!self::canTransition($currentStatus, $toStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Transition from '{$currentStatus}' to '{$toStatus}' is not allowed."],
            ]);
        }

        // Rejection requires meaningful remarks
        if ($toStatus === 'Rejected') {
            $trimmedRemarks = trim($remarks ?? '');
            if (empty($trimmedRemarks)) {
                throw ValidationException::withMessages([
                    'remarks' => ['Rejection remarks are required when marking a candidate as Rejected.'],
                ]);
            }
        }

        // Hired prerequisite verification
        if ($toStatus === 'Hired') {
            $prerequisiteError = self::validateHiredPrerequisites($candidate);
            if ($prerequisiteError !== null) {
                throw ValidationException::withMessages([
                    'status' => [$prerequisiteError],
                ]);
            }
        }

        // Execute atomically in a database transaction
        return DB::transaction(function () use ($candidate, $currentStatus, $toStatus, $remarks, $user) {
            // Update candidate status
            $candidate->status = $toStatus;
            $candidate->save();

            // Create status history record
            $candidate->statusHistories()->create([
                'from_status' => $currentStatus,
                'to_status' => $toStatus,
                'changed_by' => $user?->id,
                'remarks' => $remarks ? trim($remarks) : null,
                'changed_at' => now(),
            ]);

            // Create security audit log
            AuditService::logModelChange(
                'candidate.status_changed',
                $candidate,
                ['status' => $currentStatus],
                ['status' => $toStatus, 'remarks' => $remarks],
                "Candidate status transitioned from {$currentStatus} to {$toStatus}" . ($remarks ? ": {$remarks}" : '')
            );

            return $candidate->load(['statusHistories.changedBy', 'jobOpening.department']);
        });
    }
}
