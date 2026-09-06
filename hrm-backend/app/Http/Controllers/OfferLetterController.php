<?php

namespace App\Http\Controllers;

use App\Http\Requests\RespondOfferLetterRequest;
use App\Http\Requests\StoreOfferLetterRequest;
use App\Http\Requests\UpdateOfferLetterRequest;
use App\Models\OfferLetter;
use App\Services\AuditService;
use App\Services\OfferLetterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OfferLetterController extends Controller
{
    /**
     * Display a listing of offer letters with filters and pagination.
     */
    public function index(Request $request, OfferLetterService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('recruitment.offer_letters.view')) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        // Server-side check: auto-expire overdue Sent offers
        $service->checkAndExpireOverdueOffers();

        $query = OfferLetter::with([
            'candidate',
            'jobOpening.department',
            'department',
            'creator',
        ]);

        if ($request->filled('candidate_id')) {
            $query->candidate($request->candidate_id);
        }

        if ($request->filled('job_opening_id')) {
            $query->jobOpening($request->job_opening_id);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('offer_status')) {
            $query->status($request->offer_status);
        }

        if ($request->filled('offer_date_from')) {
            $query->whereDate('offer_date', '>=', $request->offer_date_from);
        }

        if ($request->filled('offer_date_to')) {
            $query->whereDate('offer_date', '<=', $request->offer_date_to);
        }

        if ($request->filled('joining_date_from')) {
            $query->whereDate('joining_date', '>=', $request->joining_date_from);
        }

        if ($request->filled('joining_date_to')) {
            $query->whereDate('joining_date', '<=', $request->joining_date_to);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $perPage = (int) $request->input('per_page', 15);
        $offers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($offers, Response::HTTP_OK);
    }

    /**
     * Store a newly created offer letter in storage.
     */
    public function store(StoreOfferLetterRequest $request, OfferLetterService $service): JsonResponse
    {
        $offerCode = $service->generateOfferCode();

        $offerLetter = DB::transaction(function () use ($request, $offerCode, $service) {
            $data = $request->validated();
            $data['offer_code'] = $offerCode;
            $data['offer_status'] = 'Draft';
            $data['created_by'] = $request->user()?->id;

            $offer = OfferLetter::create($data);

            // Pre-generate PDF document for the draft
            $service->generateDocument($offer);

            AuditService::logModelChange(
                'offer_letter.created',
                $offer,
                [],
                $offer->fresh()->toArray(),
                "Offer letter '{$offer->offer_code}' created for candidate '{$offer->candidate->full_name}'."
            );

            return $offer;
        });

        return response()->json([
            'message' => 'Offer letter created successfully.',
            'data' => $offerLetter->fresh(['candidate', 'jobOpening.department', 'department', 'creator']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified offer letter.
     */
    public function show(Request $request, string $id, OfferLetterService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('recruitment.offer_letters.view')) {
            return response()->json(['message' => 'Unauthorized.'], Response::HTTP_FORBIDDEN);
        }

        $offerLetter = OfferLetter::with([
            'candidate',
            'jobOpening.department',
            'department',
            'creator',
        ])->findOrFail($id);

        // Server-side check: if Sent and overdue, auto-expire
        if ($offerLetter->offer_status === 'Sent' && $offerLetter->expiry_date && $offerLetter->expiry_date->isPast()) {
            $offerLetter = $service->expire($offerLetter, 'Offer automatically expired based on expiry date.');
        }

        return response()->json([
            'data' => $offerLetter,
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified offer letter in storage (Draft only).
     */
    public function update(UpdateOfferLetterRequest $request, string $id, OfferLetterService $service): JsonResponse
    {
        $offerLetter = OfferLetter::findOrFail($id);

        $updated = DB::transaction(function () use ($request, $offerLetter, $service) {
            $oldValues = $offerLetter->toArray();
            $offerLetter->update($request->validated());

            // Re-generate PDF document with updated details
            $service->generateDocument($offerLetter);

            AuditService::logModelChange(
                'offer_letter.updated',
                $offerLetter,
                $oldValues,
                $offerLetter->fresh()->toArray(),
                "Offer letter '{$offerLetter->offer_code}' details updated."
            );

            return $offerLetter;
        });

        return response()->json([
            'message' => 'Offer letter updated successfully.',
            'data' => $updated->fresh(['candidate', 'jobOpening.department', 'department', 'creator']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified offer letter from storage (Draft only).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('recruitment.offer_letters.delete')) {
            return response()->json(['message' => 'Unauthorized to delete offer letters.'], Response::HTTP_FORBIDDEN);
        }

        $offerLetter = OfferLetter::findOrFail($id);

        if ($offerLetter->offer_status !== 'Draft') {
            return response()->json([
                'message' => "Cannot delete offer letter: Current status is '{$offerLetter->offer_status}'. Only 'Draft' offers may be deleted.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $oldValues = $offerLetter->toArray();
        $offerLetter->delete();

        AuditService::logModelChange(
            'offer_letter.deleted',
            $offerLetter,
            $oldValues,
            [],
            "Offer letter '{$offerLetter->offer_code}' deleted."
        );

        return response()->json([
            'message' => 'Offer letter deleted successfully.',
        ], Response::HTTP_OK);
    }

    /**
     * Send a Draft offer letter: Draft -> Sent
     */
    public function send(Request $request, string $id, OfferLetterService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('recruitment.offer_letters.send')) {
            return response()->json(['message' => 'Unauthorized to send offer letters.'], Response::HTTP_FORBIDDEN);
        }

        $offerLetter = OfferLetter::findOrFail($id);
        $updated = $service->send($offerLetter, $user);

        return response()->json([
            'message' => "Offer letter '{$updated->offer_code}' sent successfully.",
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Accept a Sent offer letter: Sent -> Accepted
     */
    public function accept(RespondOfferLetterRequest $request, string $id, OfferLetterService $service): JsonResponse
    {
        $offerLetter = OfferLetter::findOrFail($id);
        $updated = $service->accept($offerLetter, $request->input('response_remarks'), $request->user());

        return response()->json([
            'message' => "Offer letter '{$updated->offer_code}' marked as Accepted successfully.",
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Reject a Sent offer letter: Sent -> Rejected (requires remarks)
     */
    public function reject(RespondOfferLetterRequest $request, string $id, OfferLetterService $service): JsonResponse
    {
        $offerLetter = OfferLetter::findOrFail($id);
        $updated = $service->reject($offerLetter, (string) $request->input('response_remarks'), $request->user());

        return response()->json([
            'message' => "Offer letter '{$updated->offer_code}' marked as Rejected successfully.",
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Withdraw a Sent offer letter: Sent -> Withdrawn
     */
    public function withdraw(Request $request, string $id, OfferLetterService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->can('recruitment.offer_letters.update') && !$user->can('recruitment.offer_letters.send'))) {
            return response()->json(['message' => 'Unauthorized to withdraw offer letters.'], Response::HTTP_FORBIDDEN);
        }

        $offerLetter = OfferLetter::findOrFail($id);
        $updated = $service->withdraw($offerLetter, $request->input('response_remarks'), $user);

        return response()->json([
            'message' => "Offer letter '{$updated->offer_code}' withdrawn successfully.",
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Mark a Sent offer letter as expired: Sent -> Expired
     */
    public function expire(Request $request, string $id, OfferLetterService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->can('recruitment.offer_letters.update')) {
            return response()->json(['message' => 'Unauthorized to expire offer letters.'], Response::HTTP_FORBIDDEN);
        }

        $offerLetter = OfferLetter::findOrFail($id);
        $updated = $service->expire($offerLetter, $request->input('response_remarks'), $user);

        return response()->json([
            'message' => "Offer letter '{$updated->offer_code}' marked as Expired successfully.",
            'data' => $updated,
        ], Response::HTTP_OK);
    }

    /**
     * Download the generated offer letter PDF from private storage.
     */
    public function download(Request $request, string $id, OfferLetterService $service): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->can('recruitment.offer_letters.download') && !$user->can('recruitment.offer_letters.view'))) {
            return response()->json(['message' => 'Unauthorized to download offer letters.'], Response::HTTP_FORBIDDEN);
        }

        $offerLetter = OfferLetter::findOrFail($id);

        if (empty($offerLetter->document_path) || !Storage::disk('local')->exists($offerLetter->document_path)) {
            // Generate document on the fly if not yet created
            $service->generateDocument($offerLetter);
        }

        $absolutePath = Storage::disk('local')->path($offerLetter->document_path);

        if (!file_exists($absolutePath)) {
            return response()->json(['message' => 'Offer letter document file not found.'], Response::HTTP_NOT_FOUND);
        }

        AuditService::log(
            'offer_letter.downloaded',
            "Offer letter document '{$offerLetter->offer_code}' downloaded by user '{$user->name}'.",
            [
                'entity_type' => OfferLetter::class,
                'entity_id' => $offerLetter->id,
            ]
        );

        $filename = $offerLetter->document_original_name ?: "{$offerLetter->offer_code}.pdf";

        return response()->download($absolutePath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
