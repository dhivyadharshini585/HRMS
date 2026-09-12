<?php

namespace App\Http\Controllers;

use App\Models\Onboarding;
use App\Models\OnboardingChecklistItem;
use App\Models\OfferLetter;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OnboardingController extends Controller
{
    protected $onboardingService;

    public function __construct(OnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Onboarding::with([
            'candidate',
            'offerLetter.jobOpening',
            'employee'
        ]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('candidate', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('joining_date_from')) {
            $query->whereDate('joining_date', '>=', $request->joining_date_from);
        }

        if ($request->filled('joining_date_to')) {
            $query->whereDate('joining_date', '<=', $request->joining_date_to);
        }

        $onboardings = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json($onboardings);
    }

    /**
     * Store a newly created resource in storage (Start Onboarding).
     */
    public function store(Request $request)
    {
        $request->validate([
            'offer_letter_id' => 'required|exists:offer_letters,id',
        ]);

        $offer = OfferLetter::findOrFail($request->offer_letter_id);
        
        try {
            $onboarding = $this->onboardingService->startOnboarding($offer, $request->user());
            return response()->json([
                'message' => 'Onboarding started successfully.',
                'data' => $onboarding->load(['candidate', 'offerLetter', 'checklistItems'])
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $onboarding = Onboarding::with([
            'candidate',
            'offerLetter.jobOpening',
            'offerLetter.department',
            'employee',
            'checklistItems.document',
            'checklistItems.verifier'
        ])->findOrFail($id);

        return response()->json($onboarding);
    }

    /**
     * Cancel Onboarding
     */
    public function cancel(Request $request, string $id)
    {
        $onboarding = Onboarding::findOrFail($id);
        
        try {
            $onboarding = $this->onboardingService->cancelOnboarding($onboarding, $request->user());
            return response()->json([
                'message' => 'Onboarding cancelled successfully.',
                'data' => $onboarding
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Submit a checklist document
     */
    public function submitChecklist(Request $request, string $id, string $itemId)
    {
        $request->validate([
            'document' => 'required|file|max:5120', // 5MB max
        ]);

        $onboarding = Onboarding::findOrFail($id);
        $item = $onboarding->checklistItems()->findOrFail($itemId);

        try {
            $item = $this->onboardingService->submitChecklistDocument($item, $request->file('document'), $request->user());
            return response()->json([
                'message' => 'Document submitted successfully.',
                'data' => $item->load('document')
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Verify a checklist document
     */
    public function verifyChecklist(Request $request, string $id, string $itemId)
    {
        $onboarding = Onboarding::findOrFail($id);
        $item = $onboarding->checklistItems()->findOrFail($itemId);

        try {
            $item = $this->onboardingService->verifyChecklistItem($item, $request->user());
            return response()->json([
                'message' => 'Document verified successfully.',
                'data' => $item->load('verifier')
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Reject a checklist document
     */
    public function rejectChecklist(Request $request, string $id, string $itemId)
    {
        $request->validate([
            'remarks' => 'required|string|max:1000',
        ]);

        $onboarding = Onboarding::findOrFail($id);
        $item = $onboarding->checklistItems()->findOrFail($itemId);

        try {
            $item = $this->onboardingService->rejectChecklistItem($item, $request->remarks, $request->user());
            return response()->json([
                'message' => 'Document rejected successfully.',
                'data' => $item
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update IT Account tracking
     */
    public function updateItAccount(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Completed,Not Applicable',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $onboarding = Onboarding::findOrFail($id);

        try {
            $onboarding = $this->onboardingService->updateItAccount($onboarding, $request->status, $request->remarks, $request->user());
            return response()->json([
                'message' => 'IT Account tracking updated successfully.',
                'data' => $onboarding
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update Laptop Allocation tracking
     */
    public function updateLaptopAllocation(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Completed,Not Applicable',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $onboarding = Onboarding::findOrFail($id);

        try {
            $onboarding = $this->onboardingService->updateLaptopAllocation($onboarding, $request->status, $request->remarks, $request->user());
            return response()->json([
                'message' => 'Laptop Allocation tracking updated successfully.',
                'data' => $onboarding
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Create Employee Record
     */
    public function createEmployee(Request $request, string $id)
    {
        $onboarding = Onboarding::findOrFail($id);

        try {
            $employee = $this->onboardingService->createEmployee($onboarding, $request->user());
            return response()->json([
                'message' => 'Employee created successfully.',
                'data' => $employee
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Complete Onboarding
     */
    public function complete(Request $request, string $id)
    {
        $onboarding = Onboarding::findOrFail($id);

        try {
            $onboarding = $this->onboardingService->completeOnboarding($onboarding, $request->user());
            return response()->json([
                'message' => 'Onboarding completed successfully.',
                'data' => $onboarding
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
    
    /**
     * Download an onboarding document
     */
    public function downloadDocument(string $id, string $itemId)
    {
        $onboarding = Onboarding::findOrFail($id);
        $item = $onboarding->checklistItems()->findOrFail($itemId);
        
        $document = $item->document;
        
        if (!$document) {
            return response()->json(['message' => 'Document not found.'], 404);
        }
        
        $path = storage_path('app/private/' . $document->file_path);
        
        if (!file_exists($path)) {
            return response()->json(['message' => 'File not found on server.'], 404);
        }
        
        return response()->download($path, $document->original_name);
    }
}
