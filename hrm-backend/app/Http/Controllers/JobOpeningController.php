<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobOpeningRequest;
use App\Http\Requests\UpdateJobOpeningRequest;
use App\Models\JobOpening;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class JobOpeningController extends Controller
{
    /**
     * Display a listing of job openings with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = JobOpening::with(['department', 'designation', 'creator']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('department_id')) {
            $query->department($request->department_id);
        }

        if ($request->filled('employment_type')) {
            $query->employmentType($request->employment_type);
        }

        $perPage = (int) $request->input('per_page', 10);
        $jobOpenings = $query->latest('id')->paginate($perPage);

        return response()->json($jobOpenings);
    }

    /**
     * Store a newly created job opening.
     */
    public function store(StoreJobOpeningRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (empty($validated['job_code'])) {
            $nextId = (JobOpening::withTrashed()->max('id') ?? 0) + 1;
            $validated['job_code'] = 'JOB-' . date('Y') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        }

        $validated['created_by'] = $request->user()?->id;

        $jobOpening = JobOpening::create($validated);
        $jobOpening->load(['department', 'designation', 'creator']);

        AuditService::logModelChange(
            'created',
            $jobOpening,
            [],
            $jobOpening->toArray(),
            'Job opening created: ' . $jobOpening->title
        );

        return response()->json([
            'message' => 'Job opening created successfully',
            'data' => $jobOpening,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified job opening.
     */
    public function show(string $id): JsonResponse
    {
        $jobOpening = JobOpening::with(['department', 'designation', 'creator'])->findOrFail($id);

        $responseData = array_merge(
            ['data' => $jobOpening],
            $jobOpening->toArray()
        );

        return response()->json($responseData);
    }

    /**
     * Update the specified job opening.
     */
    public function update(UpdateJobOpeningRequest $request, string $id): JsonResponse
    {
        $jobOpening = JobOpening::findOrFail($id);
        $oldValues = $jobOpening->toArray();

        $validated = $request->validated();
        $jobOpening->update($validated);
        $jobOpening->load(['department', 'designation', 'creator']);

        $statusChanged = isset($oldValues['status']) && $oldValues['status'] !== $jobOpening->status;
        $description = 'Job opening updated: ' . $jobOpening->title;
        if ($statusChanged) {
            $description .= " (Status changed from {$oldValues['status']} to {$jobOpening->status})";
        }

        AuditService::logModelChange(
            'updated',
            $jobOpening,
            $oldValues,
            $jobOpening->toArray(),
            $description
        );

        return response()->json([
            'message' => 'Job opening updated successfully',
            'data' => $jobOpening,
        ]);
    }

    /**
     * Remove the specified job opening (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $jobOpening = JobOpening::findOrFail($id);
        $oldValues = $jobOpening->toArray();

        $jobOpening->delete();

        AuditService::logModelChange(
            'deleted',
            $jobOpening,
            $oldValues,
            [],
            'Job opening deleted: ' . $jobOpening->title
        );

        return response()->json([
            'message' => 'Job opening deleted successfully',
        ]);
    }
}
