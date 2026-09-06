<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInterviewRequest;
use App\Http\Requests\UpdateInterviewRequest;
use App\Models\Employee;
use App\Models\Interview;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InterviewController extends Controller
{
    /**
     * Display a listing of interviews with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Interview::with([
            'candidate',
            'jobOpening.department',
            'interviewer.department',
            'creator',
            'feedback',
        ]);

        $user = $request->user();
        $isHR = $user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive']);

        // Restricted manager scope: only manager as interviewer or manager's direct reports
        if (!$isHR) {
            if ($user->hasRole('Manager')) {
                $managerEmployee = $user->employee;
                $managerEmployeeId = $managerEmployee?->id ?? 0;
                $directReportIds = Employee::where('manager_id', $managerEmployeeId)->pluck('id')->toArray();
                $allowedInterviewerIds = array_merge([$managerEmployeeId], $directReportIds);

                $query->whereIn('interviewer_employee_id', $allowedInterviewerIds);
            } else {
                return response()->json(['message' => 'Unauthorized access to interviews.'], Response::HTTP_FORBIDDEN);
            }
        }

        // Filters
        if ($request->filled('candidate_id')) {
            $query->candidate($request->candidate_id);
        }

        if ($request->filled('job_opening_id')) {
            $query->jobOpening($request->job_opening_id);
        }

        if ($request->filled('interviewer_employee_id')) {
            $query->interviewer($request->interviewer_employee_id);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('interview_type')) {
            $query->interviewType($request->interview_type);
        }

        if ($request->filled('from_date') || $request->filled('to_date')) {
            $query->dateRange($request->from_date, $request->to_date);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $perPage = (int) $request->input('per_page', 10);
        $interviews = $query->orderBy('scheduled_at', 'asc')->paginate($perPage);

        return response()->json($interviews);
    }

    /**
     * Store a newly created interview schedule.
     */
    public function store(StoreInterviewRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()?->id;

        if (empty($validated['status'])) {
            $validated['status'] = 'Scheduled';
        }

        $interview = Interview::create($validated);
        $interview->load([
            'candidate',
            'jobOpening.department',
            'interviewer.department',
            'creator',
        ]);

        AuditService::logModelChange(
            'interview.created',
            $interview,
            [],
            $interview->toArray(),
            'Interview scheduled for candidate: ' . ($interview->candidate?->full_name ?? $interview->candidate_id)
        );

        return response()->json([
            'message' => 'Interview scheduled successfully',
            'data' => $interview,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified interview.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $interview = Interview::with([
            'candidate',
            'jobOpening.department',
            'interviewer.department',
            'creator',
            'feedback',
        ])->findOrFail($id);

        $user = $request->user();
        $isHR = $user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive']);

        if (!$isHR) {
            if ($user->hasRole('Manager')) {
                $managerEmployee = $user->employee;
                $managerEmployeeId = $managerEmployee?->id ?? 0;
                $directReportIds = Employee::where('manager_id', $managerEmployeeId)->pluck('id')->toArray();
                $allowedInterviewerIds = array_merge([$managerEmployeeId], $directReportIds);

                if (!in_array($interview->interviewer_employee_id, $allowedInterviewerIds)) {
                    return response()->json([
                        'message' => 'Unauthorized access to this interview schedule.',
                    ], Response::HTTP_FORBIDDEN);
                }
            } else {
                return response()->json([
                    'message' => 'Unauthorized access to interviews.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        return response()->json([
            'data' => $interview,
        ]);
    }

    /**
     * Update the specified interview.
     */
    public function update(UpdateInterviewRequest $request, string $id): JsonResponse
    {
        $interview = Interview::findOrFail($id);
        $oldValues = $interview->toArray();

        $validated = $request->validated();
        $interview->update($validated);
        $interview->load([
            'candidate',
            'jobOpening.department',
            'interviewer.department',
            'creator',
        ]);

        $candidateName = $interview->candidate?->full_name ?? "Candidate #{$interview->candidate_id}";

        // Determine specific audit action
        $scheduledChanged = isset($validated['scheduled_at']) && $validated['scheduled_at'] != $oldValues['scheduled_at'];
        $statusChanged = isset($validated['status']) && $validated['status'] !== $oldValues['status'];

        if ($statusChanged && $interview->status === 'Cancelled') {
            $action = 'interview.cancelled';
            $description = "Interview cancelled for candidate: {$candidateName}";
        } elseif ($scheduledChanged || ($statusChanged && $interview->status === 'Rescheduled')) {
            $action = 'interview.rescheduled';
            $description = "Interview rescheduled for candidate: {$candidateName}";
        } else {
            $action = 'interview.updated';
            $description = "Interview updated for candidate: {$candidateName}";
        }

        AuditService::logModelChange(
            $action,
            $interview,
            $oldValues,
            $interview->toArray(),
            $description
        );

        return response()->json([
            'message' => 'Interview updated successfully',
            'data' => $interview,
        ]);
    }

    /**
     * Remove the specified interview (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $interview = Interview::findOrFail($id);
        $oldValues = $interview->toArray();
        $candidateName = $interview->candidate?->full_name ?? "Candidate #{$interview->candidate_id}";

        $interview->delete();

        AuditService::logModelChange(
            'interview.deleted',
            $interview,
            $oldValues,
            [],
            "Interview deleted for candidate: {$candidateName}"
        );

        return response()->json([
            'message' => 'Interview deleted successfully',
        ]);
    }
}
