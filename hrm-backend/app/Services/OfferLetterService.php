<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\JobOpening;
use App\Models\OfferLetter;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OfferLetterService
{
    /**
     * Generate a unique sequential offer code: OFF-{YYYY}-{000001}
     */
    public function generateOfferCode(?int $year = null): string
    {
        $year = $year ?: (int) date('Y');
        $prefix = "OFF-{$year}-";

        // Query the highest existing sequence number for this year, including soft-deleted records
        $latest = OfferLetter::withTrashed()
            ->where('offer_code', 'like', "{$prefix}%")
            ->orderBy('offer_code', 'desc')
            ->value('offer_code');

        $sequence = 1;
        if ($latest && preg_match("/^OFF-{$year}-(\d{6})$/", $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        $candidateCode = sprintf("OFF-%d-%06d", $year, $sequence);

        // Guarantee uniqueness in rare race condition
        while (OfferLetter::withTrashed()->where('offer_code', $candidateCode)->exists()) {
            $sequence++;
            $candidateCode = sprintf("OFF-%d-%06d", $year, $sequence);
        }

        return $candidateCode;
    }

    /**
     * Validate candidate and job opening eligibility for creating a new offer letter.
     *
     * @throws ValidationException
     */
    public function validateCandidateEligibility(int $candidateId, int $jobOpeningId, ?int $ignoreOfferId = null): Candidate
    {
        $candidate = Candidate::find($candidateId);

        if (!$candidate) {
            throw ValidationException::withMessages([
                'candidate_id' => ['The selected candidate does not exist.'],
            ]);
        }

        // Must be in 'Hired' status
        if ($candidate->status !== 'Hired') {
            throw ValidationException::withMessages([
                'candidate_id' => ["Cannot create an offer for candidate with status '{$candidate->status}'. Candidate must be in 'Hired' status."],
            ]);
        }

        $jobOpening = JobOpening::find($jobOpeningId);
        if (!$jobOpening) {
            throw ValidationException::withMessages([
                'job_opening_id' => ['The selected job opening does not exist.'],
            ]);
        }

        // Must match candidate's job opening
        if ((int) $candidate->job_opening_id !== (int) $jobOpeningId) {
            throw ValidationException::withMessages([
                'job_opening_id' => ["The selected job opening does not match the candidate's assigned job opening."],
            ]);
        }

        // ONE ACTIVE OFFER RULE: Candidate must not already have a Draft or Sent offer
        $activeQuery = OfferLetter::where('candidate_id', $candidateId)
            ->whereIn('offer_status', ['Draft', 'Sent']);

        if ($ignoreOfferId) {
            $activeQuery->where('id', '!=', $ignoreOfferId);
        }

        if ($activeQuery->exists()) {
            throw ValidationException::withMessages([
                'candidate_id' => ['Candidate already has an active offer letter (status: Draft or Sent). Multiple active offers for the same candidate are not permitted.'],
            ]);
        }

        return $candidate;
    }

    /**
     * Render and securely store the offer letter PDF in private storage.
     */
    public function generateDocument(OfferLetter $offerLetter): string
    {
        $offerLetter->loadMissing([
            'candidate',
            'jobOpening.department',
            'department',
            'creator',
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $html = view('offer_letters.pdf', ['offer' => $offerLetter])->render();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();

        // Save into private storage
        $filename = "{$offerLetter->offer_code}.pdf";
        $storageRelativePath = "private/offer-letters/{$filename}";

        Storage::disk('local')->put($storageRelativePath, $pdfContent);

        $offerLetter->updateQuietly([
            'document_path' => $storageRelativePath,
            'document_original_name' => $filename,
        ]);

        return $storageRelativePath;
    }

    /**
     * Send a Draft offer letter: Draft -> Sent
     *
     * @throws ValidationException
     */
    public function send(OfferLetter $offerLetter, ?User $user = null): OfferLetter
    {
        if ($offerLetter->offer_status !== 'Draft') {
            throw ValidationException::withMessages([
                'offer_status' => ["Cannot send offer letter: Current status is '{$offerLetter->offer_status}'. Only 'Draft' offers may be sent."],
            ]);
        }

        return DB::transaction(function () use ($offerLetter, $user) {
            $oldValues = $offerLetter->toArray();

            // Ensure document is generated before sending
            if (empty($offerLetter->document_path) || !Storage::disk('local')->exists($offerLetter->document_path)) {
                $this->generateDocument($offerLetter);
            }

            $offerLetter->update([
                'offer_status' => 'Sent',
                'sent_at' => now(),
            ]);

            AuditService::logModelChange(
                'offer_letter.sent',
                $offerLetter,
                $oldValues,
                $offerLetter->fresh()->toArray(),
                "Offer letter '{$offerLetter->offer_code}' sent to candidate '{$offerLetter->candidate->full_name}'."
            );

            return $offerLetter->fresh(['candidate', 'jobOpening.department', 'department', 'creator']);
        });
    }

    /**
     * Accept a Sent offer letter: Sent -> Accepted
     *
     * @throws ValidationException
     */
    public function accept(OfferLetter $offerLetter, ?string $remarks = null, ?User $user = null): OfferLetter
    {
        if ($offerLetter->offer_status !== 'Sent') {
            throw ValidationException::withMessages([
                'offer_status' => ["Cannot accept offer letter: Current status is '{$offerLetter->offer_status}'. Only 'Sent' offers may be accepted."],
            ]);
        }

        return DB::transaction(function () use ($offerLetter, $remarks, $user) {
            $oldValues = $offerLetter->toArray();

            $offerLetter->update([
                'offer_status' => 'Accepted',
                'responded_at' => now(),
                'response_remarks' => $remarks,
            ]);

            // IMPORTANT: Candidate status MUST remain 'Hired'.
            // DO NOT create onboarding, employee, or payroll records.

            AuditService::logModelChange(
                'offer_letter.accepted',
                $offerLetter,
                $oldValues,
                $offerLetter->fresh()->toArray(),
                "Offer letter '{$offerLetter->offer_code}' marked as Accepted."
            );

            return $offerLetter->fresh(['candidate', 'jobOpening.department', 'department', 'creator']);
        });
    }

    /**
     * Reject a Sent offer letter: Sent -> Rejected (requires remarks)
     *
     * @throws ValidationException
     */
    public function reject(OfferLetter $offerLetter, string $remarks, ?User $user = null): OfferLetter
    {
        if ($offerLetter->offer_status !== 'Sent') {
            throw ValidationException::withMessages([
                'offer_status' => ["Cannot reject offer letter: Current status is '{$offerLetter->offer_status}'. Only 'Sent' offers may be rejected."],
            ]);
        }

        if (empty(trim($remarks))) {
            throw ValidationException::withMessages([
                'response_remarks' => ['Response remarks / reason are required when rejecting an offer letter.'],
            ]);
        }

        return DB::transaction(function () use ($offerLetter, $remarks, $user) {
            $oldValues = $offerLetter->toArray();

            $offerLetter->update([
                'offer_status' => 'Rejected',
                'responded_at' => now(),
                'response_remarks' => trim($remarks),
            ]);

            // Candidate status remains 'Hired'
            AuditService::logModelChange(
                'offer_letter.rejected',
                $offerLetter,
                $oldValues,
                $offerLetter->fresh()->toArray(),
                "Offer letter '{$offerLetter->offer_code}' marked as Rejected with remarks: '{$remarks}'."
            );

            return $offerLetter->fresh(['candidate', 'jobOpening.department', 'department', 'creator']);
        });
    }

    /**
     * Withdraw a Sent offer letter: Sent -> Withdrawn
     *
     * @throws ValidationException
     */
    public function withdraw(OfferLetter $offerLetter, ?string $remarks = null, ?User $user = null): OfferLetter
    {
        if ($offerLetter->offer_status !== 'Sent') {
            throw ValidationException::withMessages([
                'offer_status' => ["Cannot withdraw offer letter: Current status is '{$offerLetter->offer_status}'. Only 'Sent' offers may be withdrawn."],
            ]);
        }

        return DB::transaction(function () use ($offerLetter, $remarks, $user) {
            $oldValues = $offerLetter->toArray();

            $offerLetter->update([
                'offer_status' => 'Withdrawn',
                'responded_at' => now(),
                'response_remarks' => $remarks ? trim($remarks) : null,
            ]);

            AuditService::logModelChange(
                'offer_letter.withdrawn',
                $offerLetter,
                $oldValues,
                $offerLetter->fresh()->toArray(),
                "Offer letter '{$offerLetter->offer_code}' withdrawn."
            );

            return $offerLetter->fresh(['candidate', 'jobOpening.department', 'department', 'creator']);
        });
    }

    /**
     * Expire a Sent offer letter: Sent -> Expired (determined server-side)
     *
     * @throws ValidationException
     */
    public function expire(OfferLetter $offerLetter, ?string $remarks = null, ?User $user = null): OfferLetter
    {
        if ($offerLetter->offer_status !== 'Sent') {
            throw ValidationException::withMessages([
                'offer_status' => ["Cannot expire offer letter: Current status is '{$offerLetter->offer_status}'. Only 'Sent' offers may be marked as expired."],
            ]);
        }

        // Expiration must be determined server-side: expiry_date must not be in the future
        if ($offerLetter->expiry_date && $offerLetter->expiry_date->isFuture()) {
            throw ValidationException::withMessages([
                'expiry_date' => ["Cannot expire offer letter: The expiry date ({$offerLetter->expiry_date->toDateString()}) has not yet passed."],
            ]);
        }

        return DB::transaction(function () use ($offerLetter, $remarks, $user) {
            $oldValues = $offerLetter->toArray();

            $offerLetter->update([
                'offer_status' => 'Expired',
                'responded_at' => now(),
                'response_remarks' => $remarks ? trim($remarks) : 'Offer expired.',
            ]);

            AuditService::logModelChange(
                'offer_letter.expired',
                $offerLetter,
                $oldValues,
                $offerLetter->fresh()->toArray(),
                "Offer letter '{$offerLetter->offer_code}' marked as Expired."
            );

            return $offerLetter->fresh(['candidate', 'jobOpening.department', 'department', 'creator']);
        });
    }

    /**
     * Automatically scan and expire overdue Sent offers server-side.
     */
    public function checkAndExpireOverdueOffers(): int
    {
        $overdueOffers = OfferLetter::where('offer_status', 'Sent')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($overdueOffers as $offer) {
            try {
                $this->expire($offer, 'Offer automatically expired based on expiry date.');
                $count++;
            } catch (\Exception $e) {
                // Log and continue
            }
        }

        return $count;
    }
}
