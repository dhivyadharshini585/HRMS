<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employee;
use App\Models\OfferLetter;
use App\Models\Onboarding;
use App\Models\OnboardingChecklistItem;
use App\Models\OnboardingDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;

class OnboardingService
{
    /**
     * Start the onboarding process for an accepted offer.
     */
    public function startOnboarding(OfferLetter $offer, $user): Onboarding
    {
        if ($offer->offer_status !== 'Accepted') {
            throw new Exception("Onboarding can only be started for an Accepted offer. Current status is {$offer->offer_status}.");
        }

        if (Onboarding::where('offer_letter_id', $offer->id)->exists()) {
            throw new Exception("Onboarding process already exists for this offer.");
        }

        return DB::transaction(function () use ($offer, $user) {
            $onboarding = Onboarding::create([
                'candidate_id' => $offer->candidate_id,
                'offer_letter_id' => $offer->id,
                'joining_date' => $offer->joining_date,
                'status' => 'In Progress',
                'started_at' => now(),
                'created_by' => $user->id,
            ]);

            // Create checklist items
            $itemTypes = [
                'Offer Letter',
                'ID Proof',
                'Address Proof',
                'PAN',
                'Bank Details',
                'Educational Certificates',
                'Previous Experience Documents',
                'Photograph',
                'NDA',
                'Company Policy Acceptance'
            ];

            foreach ($itemTypes as $type) {
                $status = 'Pending';
                
                // If it's the Offer Letter, auto-verify if document exists
                if ($type === 'Offer Letter' && $offer->has_document) {
                    $status = 'Verified';
                }

                $onboarding->checklistItems()->create([
                    'item_type' => $type,
                    'status' => $status,
                    'verified_by' => $status === 'Verified' ? $user->id : null,
                    'verified_at' => $status === 'Verified' ? now() : null,
                ]);
            }

            AuditService::logModelChange('onboarding.started', $onboarding, [], $onboarding->toArray(), "Onboarding started for candidate {$offer->candidate->full_name}.");

            return $onboarding;
        });
    }

    /**
     * Cancel the onboarding process.
     */
    public function cancelOnboarding(Onboarding $onboarding, $user): Onboarding
    {
        if (in_array($onboarding->status, ['Completed', 'Cancelled'])) {
            throw new Exception("Cannot cancel onboarding. Current status is {$onboarding->status}.");
        }

        $oldValues = $onboarding->toArray();

        $onboarding->update([
            'status' => 'Cancelled',
            'updated_by' => $user->id,
        ]);

        AuditService::logModelChange('onboarding.cancelled', $onboarding, $oldValues, $onboarding->toArray(), "Onboarding cancelled for candidate {$onboarding->candidate->full_name}.");

        return $onboarding;
    }

    /**
     * Submit a checklist document.
     */
    public function submitChecklistDocument(OnboardingChecklistItem $item, $file, $user): OnboardingChecklistItem
    {
        if ($item->status === 'Verified') {
            throw new Exception("This checklist item is already verified.");
        }

        return DB::transaction(function () use ($item, $file, $user) {
            $path = $file->store('onboarding-documents', 'local');

            $document = OnboardingDocument::create([
                'onboarding_id' => $item->onboarding_id,
                'checklist_item_id' => $item->id,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
            ]);

            $item->update([
                'document_id' => $document->id,
                'status' => 'Submitted',
                'remarks' => null, // clear previous rejection remarks
            ]);

            AuditService::logModelChange('onboarding.document_submitted', $item, [], $item->toArray(), "Document submitted for checklist item '{$item->item_type}'.");

            return $item;
        });
    }

    /**
     * Verify a checklist document.
     */
    public function verifyChecklistItem(OnboardingChecklistItem $item, $user): OnboardingChecklistItem
    {
        if ($item->status === 'Verified') {
            return $item;
        }

        $item->update([
            'status' => 'Verified',
            'verified_by' => $user->id,
            'verified_at' => now(),
            'remarks' => null,
        ]);

        AuditService::logModelChange('onboarding.document_verified', $item, [], $item->toArray(), "Checklist item '{$item->item_type}' verified.");

        return $item;
    }

    /**
     * Reject a checklist document.
     */
    public function rejectChecklistItem(OnboardingChecklistItem $item, string $remarks, $user): OnboardingChecklistItem
    {
        if (empty($remarks)) {
            throw new Exception("Remarks are required when rejecting a checklist item.");
        }

        $item->update([
            'status' => 'Rejected',
            'remarks' => $remarks,
            'verified_by' => null,
            'verified_at' => null,
        ]);

        AuditService::logModelChange('onboarding.document_rejected', $item, [], $item->toArray(), "Checklist item '{$item->item_type}' rejected: {$remarks}");

        return $item;
    }

    /**
     * Update IT Account tracking.
     */
    public function updateItAccount(Onboarding $onboarding, string $status, ?string $remarks, $user): Onboarding
    {
        $onboarding->update([
            'it_account_status' => $status,
            'it_account_remarks' => $remarks,
            'updated_by' => $user->id,
        ]);

        AuditService::logModelChange('onboarding.it_account_updated', $onboarding, [], $onboarding->toArray(), "IT account tracking updated to {$status}.");

        return $onboarding;
    }

    /**
     * Update Laptop Allocation tracking.
     */
    public function updateLaptopAllocation(Onboarding $onboarding, string $status, ?string $remarks, $user): Onboarding
    {
        $onboarding->update([
            'laptop_allocation_status' => $status,
            'laptop_allocation_remarks' => $remarks,
            'updated_by' => $user->id,
        ]);

        AuditService::logModelChange('onboarding.laptop_allocated', $onboarding, [], $onboarding->toArray(), "Laptop allocation tracking updated to {$status}.");

        return $onboarding;
    }

    /**
     * Create the final Employee record from Onboarding.
     */
    public function createEmployee(Onboarding $onboarding, $user): Employee
    {
        if ($onboarding->status === 'Cancelled') {
            throw new Exception("Cannot create employee. Onboarding is cancelled.");
        }

        if ($onboarding->offerLetter->offer_status !== 'Accepted') {
            throw new Exception("Offer Letter must be Accepted.");
        }

        if ($onboarding->employee_id) {
            throw new Exception("Employee record has already been created for this onboarding process.");
        }

        // Validate checklist
        $requiredItems = $onboarding->checklistItems()->whereIn('status', ['Pending', 'Submitted', 'Rejected'])->exists();
        if ($requiredItems) {
            throw new Exception("All required documents must be Verified before creating the employee.");
        }

        return DB::transaction(function () use ($onboarding, $user) {
            $offer = $onboarding->offerLetter;
            $candidate = $onboarding->candidate;

            $employeeCode = Employee::generateEmployeeCode();

            $employee = Employee::create([
                'employee_code' => $employeeCode,
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email, // Should we use personal or generate work email? Keeping candidate email.
                'phone' => $candidate->phone,
                'date_of_birth' => $candidate->date_of_birth,
                'gender' => $candidate->gender,
                'address' => $candidate->address,
                'date_of_joining' => $offer->joining_date,
                'department_id' => $offer->department_id,
                'employment_type' => $offer->employment_type ?? 'Full-time',
                'employment_status' => 'Probation', // Starts on probation based on joining
                'work_location' => $offer->work_location,
            ]);

            $onboarding->update([
                'employee_id' => $employee->id,
                'updated_by' => $user->id,
            ]);

            AuditService::logModelChange('onboarding.employee_created', $onboarding, [], $onboarding->toArray(), "Employee record {$employeeCode} created.");

            return $employee;
        });
    }

    /**
     * Complete the onboarding process.
     */
    public function completeOnboarding(Onboarding $onboarding, $user): Onboarding
    {
        if ($onboarding->status === 'Completed') {
            return $onboarding;
        }

        if ($onboarding->status === 'Cancelled') {
            throw new Exception("Cannot complete a cancelled onboarding.");
        }

        if (!$onboarding->employee_id) {
            throw new Exception("Employee record must be created before completing onboarding.");
        }

        if (!in_array($onboarding->it_account_status, ['Completed', 'Not Applicable'])) {
            throw new Exception("IT Account setup must be Completed or Not Applicable.");
        }

        if (!in_array($onboarding->laptop_allocation_status, ['Completed', 'Not Applicable'])) {
            throw new Exception("Laptop Allocation must be Completed or Not Applicable.");
        }

        $requiredItems = $onboarding->checklistItems()->whereIn('status', ['Pending', 'Submitted', 'Rejected'])->exists();
        if ($requiredItems) {
            throw new Exception("All required documents must be Verified.");
        }

        return DB::transaction(function () use ($onboarding, $user) {
            $onboarding->update([
                'status' => 'Completed',
                'completed_at' => now(),
                'updated_by' => $user->id,
            ]);

            // Activate employee status
            $employee = $onboarding->employee;
            if ($employee->employment_status === 'Probation' && !$onboarding->offerLetter->probation_period_months) {
                // If no probation, go straight to Active. Else stay Probation
                $employee->update(['employment_status' => 'Active']);
            }

            AuditService::logModelChange('onboarding.completed', $onboarding, [], $onboarding->toArray(), "Onboarding completed successfully.");

            return $onboarding;
        });
    }
}
