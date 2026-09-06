<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInterviewFeedbackRequest;
use App\Http\Requests\UpdateInterviewFeedbackRequest;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewFeedback;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterviewFeedbackController extends Controller
{
    /**
     * Display the feedback for the specified interview.
     */
    public function show(Request $request, $interviewId): JsonResponse
    {
        $user = $request->user();
        if (!$user->can('recruitment.interview_feedback.view')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $interview = Interview::with(['candidate', 'jobOpening', 'interviewer'])->findOrFail($interviewId);

        // Manager scope check: Manager can view feedback only within their existing interview scope
        if ($user->hasRole('Manager') && !$user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            $managerEmployee = $user->employee;
            if (!$managerEmployee) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }

            $directReportIds = Employee::where('manager_id', $managerEmployee->id)->pluck('id')->toArray();
            $allowedInterviewerIds = array_merge([$managerEmployee->id], $directReportIds);

            if (!in_array($interview->interviewer_employee_id, $allowedInterviewerIds)) {
                return response()->json(['message' => 'Unauthorized access to this interview feedback.'], 403);
            }
        }

        $feedback = InterviewFeedback::with('interviewer')
            ->where('interview_id', $interview->id)
            ->first();

        if (!$feedback) {
            return response()->json(['message' => 'No feedback found for this interview.'], 404);
        }

        return response()->json([
            'data' => $feedback,
            'interview' => [
                'id' => $interview->id,
                'candidate_id' => $interview->candidate_id,
                'candidate_name' => $interview->candidate?->full_name,
                'candidate_code' => $interview->candidate?->candidate_code,
                'job_opening_id' => $interview->job_opening_id,
                'job_title' => $interview->jobOpening?->title,
                'interviewer_employee_id' => $interview->interviewer_employee_id,
                'interviewer_name' => $interview->interviewer ? ($interview->interviewer->first_name . ' ' . $interview->interviewer->last_name) : null,
                'interview_type' => $interview->interview_type,
                'interview_round' => $interview->interview_round,
                'status' => $interview->status,
                'scheduled_at' => $interview->scheduled_at,
            ]
        ]);
    }

    /**
     * Store new feedback for the specified interview.
     */
    public function store(StoreInterviewFeedbackRequest $request, $interviewId): JsonResponse
    {
        $interview = Interview::with(['candidate', 'interviewer'])->findOrFail($interviewId);

        // Derive interviewer_employee_id server-side from the interview
        $interviewerEmployeeId = $interview->interviewer_employee_id;

        // Explicitly set submitted_at to now()
        $submittedAt = now();

        $feedback = InterviewFeedback::create([
            'interview_id' => $interview->id,
            'interviewer_employee_id' => $interviewerEmployeeId,
            'overall_rating' => $request->overall_rating,
            'technical_rating' => $request->technical_rating,
            'communication_rating' => $request->communication_rating,
            'problem_solving_rating' => $request->problem_solving_rating,
            'cultural_fit_rating' => $request->cultural_fit_rating,
            'strengths' => $request->strengths,
            'weaknesses' => $request->weaknesses,
            'comments' => $request->comments,
            'recommendation' => $request->recommendation,
            'submitted_at' => $submittedAt,
        ]);

        // Audit Logging
        AuditService::logModelChange(
            'interview_feedback.created',
            $feedback,
            [],
            $feedback->toArray(),
            "Interview feedback submitted for interview #{$interview->id} ({$interview->candidate?->full_name}, Round {$interview->interview_round}) by {$request->user()->name}. Recommendation: {$feedback->recommendation}, Overall: {$feedback->overall_rating}/5."
        );

        return response()->json([
            'message' => 'Interview feedback submitted successfully.',
            'data' => $feedback->load('interviewer'),
        ], 201);
    }

    /**
     * Update the feedback for the specified interview.
     */
    public function update(UpdateInterviewFeedbackRequest $request, $interviewId): JsonResponse
    {
        $interview = Interview::with('candidate')->findOrFail($interviewId);

        $feedback = InterviewFeedback::where('interview_id', $interview->id)->firstOrFail();
        $oldValues = $feedback->toArray();

        $feedback->fill($request->validated());
        $feedback->save();

        // Audit Logging
        AuditService::logModelChange(
            'interview_feedback.updated',
            $feedback,
            $oldValues,
            $feedback->fresh()->toArray(),
            "Interview feedback updated for interview #{$interview->id} ({$interview->candidate?->full_name}) by {$request->user()->name}."
        );

        return response()->json([
            'message' => 'Interview feedback updated successfully.',
            'data' => $feedback->load('interviewer'),
        ]);
    }

    /**
     * Soft-delete feedback for the specified interview.
     */
    public function destroy(Request $request, $interviewId): JsonResponse
    {
        $user = $request->user();
        if (!$user->can('recruitment.interview_feedback.delete')) {
            return response()->json(['message' => 'Unauthorized to delete interview feedback.'], 403);
        }

        $interview = Interview::with('candidate')->findOrFail($interviewId);
        $feedback = InterviewFeedback::where('interview_id', $interview->id)->firstOrFail();

        $oldValues = $feedback->toArray();
        $feedback->delete();

        // Audit Logging
        AuditService::logModelChange(
            'interview_feedback.deleted',
            $feedback,
            $oldValues,
            [],
            "Interview feedback deleted for interview #{$interview->id} ({$interview->candidate?->full_name}) by {$user->name}."
        );

        return response()->json([
            'message' => 'Interview feedback deleted successfully.',
        ]);
    }
}
