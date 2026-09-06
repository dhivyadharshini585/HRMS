<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Http\Requests\UpdateCandidateStatusRequest;
use App\Http\Requests\UploadResumeRequest;
use App\Models\Candidate;
use App\Services\AuditService;
use App\Services\CandidateStatusPipelineService;
use App\Services\ResumeExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateController extends Controller
{
    /**
     * Display a listing of candidates with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Candidate::with(['jobOpening.department', 'creator']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('job_opening_id')) {
            $query->jobOpening($request->job_opening_id);
        }

        if ($request->filled('source')) {
            $query->source($request->source);
        }

        $perPage = (int) $request->input('per_page', 10);
        $candidates = $query->latest('id')->paginate($perPage);

        return response()->json($candidates);
    }

    /**
     * Store a newly created candidate.
     */
    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (empty($validated['candidate_code'])) {
            $nextId = (Candidate::withTrashed()->max('id') ?? 0) + 1;
            $code = 'CAN-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            while (Candidate::withTrashed()->where('candidate_code', $code)->exists()) {
                $nextId++;
                $code = 'CAN-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
            }
            $validated['candidate_code'] = $code;
        }

        $validated['created_by'] = $request->user()?->id;

        $candidate = \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $created = Candidate::create($validated);
            $created->load(['jobOpening.department', 'creator', 'statusHistories.changedBy']);
            return $created;
        });

        AuditService::logModelChange(
            'created',
            $candidate,
            [],
            $candidate->toArray(),
            'Candidate created: ' . $candidate->full_name
        );

        return response()->json([
            'message' => 'Candidate created successfully',
            'data' => $candidate,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified candidate.
     */
    public function show(string $id): JsonResponse
    {
        $candidate = Candidate::with(['jobOpening.department', 'creator', 'statusHistories.changedBy'])->findOrFail($id);

        $responseData = array_merge(
            ['data' => $candidate],
            $candidate->toArray()
        );

        return response()->json($responseData);
    }

    /**
     * Update the specified candidate.
     */
    public function update(UpdateCandidateRequest $request, string $id): JsonResponse
    {
        $candidate = Candidate::findOrFail($id);
        $oldValues = $candidate->toArray();

        $validated = $request->validated();
        $candidate->update($validated);
        $candidate->load(['jobOpening.department', 'creator']);

        $statusChanged = isset($oldValues['status']) && $oldValues['status'] !== $candidate->status;
        $description = 'Candidate updated: ' . $candidate->full_name;
        if ($statusChanged) {
            $description .= " (Status changed from {$oldValues['status']} to {$candidate->status})";
        }

        AuditService::logModelChange(
            'updated',
            $candidate,
            $oldValues,
            $candidate->toArray(),
            $description
        );

        return response()->json([
            'message' => 'Candidate updated successfully',
            'data' => $candidate,
        ]);
    }

    /**
     * Remove the specified candidate (Soft Delete).
     */
    public function destroy(string $id): JsonResponse
    {
        $candidate = Candidate::findOrFail($id);
        $oldValues = $candidate->toArray();

        $candidate->delete();

        AuditService::logModelChange(
            'deleted',
            $candidate,
            $oldValues,
            [],
            'Candidate deleted: ' . $candidate->full_name
        );

        return response()->json([
            'message' => 'Candidate deleted successfully',
        ]);
    }

    /**
     * Upload or replace resume for the specified candidate.
     */
    public function uploadResume(UploadResumeRequest $request, string $id): JsonResponse
    {
        $candidate = Candidate::findOrFail($id);
        $file = $request->file('resume');

        // Extract suggestions gracefully (guaranteed to never fail the upload)
        $extraction = ResumeExtractionService::extract($file, $file->getClientOriginalName());

        // Store file securely in private disk 'local'
        $path = $file->store('resumes', 'local');

        $oldResumePath = $candidate->resume_path;
        $oldValues = $candidate->toArray();

        $candidate->update([
            'resume_path' => $path,
            'resume_original_name' => $file->getClientOriginalName(),
            'resume_mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'resume_size' => $file->getSize(),
            'resume_uploaded_at' => now(),
        ]);

        // Clean up previous file only after successful database record update
        if ($oldResumePath && Storage::disk('local')->exists($oldResumePath)) {
            Storage::disk('local')->delete($oldResumePath);
        }

        AuditService::logModelChange(
            $oldResumePath ? 'updated' : 'uploaded',
            $candidate,
            $oldValues,
            $candidate->toArray(),
            ($oldResumePath ? 'Resume replaced for candidate: ' : 'Resume uploaded for candidate: ') . $candidate->full_name
        );

        return response()->json([
            'message' => $oldResumePath ? 'Resume replaced successfully' : 'Resume uploaded successfully',
            'data' => $candidate->fresh(['jobOpening.department', 'creator']),
            'extraction' => $extraction,
        ], Response::HTTP_OK);
    }

    /**
     * Download resume for the specified candidate.
     */
    public function downloadResume(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $candidate = Candidate::findOrFail($id);

        if (empty($candidate->resume_path) || !Storage::disk('local')->exists($candidate->resume_path)) {
            return response()->json([
                'message' => 'Resume file not found on server',
            ], Response::HTTP_NOT_FOUND);
        }

        AuditService::log(
            'downloaded',
            'Candidate resume downloaded: ' . $candidate->full_name,
            [
                'entity_type' => Candidate::class,
                'entity_id' => $candidate->id,
                'new_values' => [
                    'candidate_code' => $candidate->candidate_code,
                    'filename' => $candidate->resume_original_name,
                ],
            ]
        );

        return Storage::disk('local')->download(
            $candidate->resume_path,
            $candidate->resume_original_name,
            [
                'Content-Type' => $candidate->resume_mime_type ?: 'application/octet-stream',
            ]
        );
    }

    /**
     * Delete resume for the specified candidate.
     */
    public function deleteResume(Request $request, string $id): JsonResponse
    {
        $candidate = Candidate::findOrFail($id);

        if (empty($candidate->resume_path)) {
            return response()->json([
                'message' => 'No resume attached to this candidate',
            ], Response::HTTP_NOT_FOUND);
        }

        $oldResumePath = $candidate->resume_path;
        $oldValues = $candidate->toArray();

        if (Storage::disk('local')->exists($oldResumePath)) {
            Storage::disk('local')->delete($oldResumePath);
        }

        $candidate->update([
            'resume_path' => null,
            'resume_original_name' => null,
            'resume_mime_type' => null,
            'resume_size' => null,
            'resume_uploaded_at' => null,
        ]);

        AuditService::logModelChange(
            'deleted',
            $candidate,
            $oldValues,
            $candidate->toArray(),
            'Candidate resume deleted: ' . $candidate->full_name
        );

        return response()->json([
            'message' => 'Resume deleted successfully',
            'data' => $candidate->fresh(['jobOpening.department', 'creator']),
        ], Response::HTTP_OK);
    }

    /**
     * Update candidate status through the pipeline state machine.
     */
    public function updateStatus(
        UpdateCandidateStatusRequest $request,
        string $id,
        CandidateStatusPipelineService $pipelineService
    ): JsonResponse {
        $candidate = Candidate::findOrFail($id);

        $updatedCandidate = $pipelineService->transition(
            $candidate,
            $request->input('status'),
            $request->input('remarks'),
            $request->user()
        );

        return response()->json([
            'message' => "Candidate status updated to '{$updatedCandidate->status}' successfully.",
            'data' => $updatedCandidate->fresh(['jobOpening.department', 'creator', 'statusHistories.changedBy']),
        ], Response::HTTP_OK);
    }

    /**
     * Display the chronological status history for the candidate.
     */
    public function statusHistory(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('recruitment.candidates.view')) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        $candidate = Candidate::findOrFail($id);

        $histories = $candidate->statusHistories()
            ->with('changedBy')
            ->orderBy('changed_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'data' => $histories,
        ], Response::HTTP_OK);
    }
}
