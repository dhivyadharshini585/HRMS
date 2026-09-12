<?php

namespace App\Http\Controllers;

use App\Models\Candidate;
use App\Models\CandidateStatusHistory;
use App\Models\Interview;
use App\Models\JobOpening;
use App\Models\OfferLetter;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecruitmentReportController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // A. Applications Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Applications report — paginated candidate list with summary counts.
     *
     * Filters: date_from, date_to (based on candidate.created_at), job_opening_id,
     *          status, source, search, per_page
     */
    public function applications(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'     => 'nullable|date',
            'date_to'       => 'nullable|date|after_or_equal:date_from',
            'job_opening_id'=> 'nullable|integer|exists:job_openings,id',
            'status'        => 'nullable|string|in:New,Screening,Shortlisted,Rejected,Hired',
            'source'        => 'nullable|string|max:100',
            'search'        => 'nullable|string|max:100',
            'per_page'      => 'nullable|integer|min:5|max:100',
        ]);

        $query = Candidate::with(['jobOpening:id,title,job_code', 'creator:id,name'])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->filled('status'),    fn($q) => $q->where('status', $request->status))
            ->when($request->filled('source'),    fn($q) => $q->where('source', $request->source))
            ->when($request->filled('search'),    fn($q) => $q->search($request->search))
            ->orderBy('created_at', 'desc');

        // Summary counts before pagination
        $allIds   = (clone $query)->pluck('status');
        $summary  = [
            'total'      => $allIds->count(),
            'by_status'  => $allIds->groupBy(fn($s) => $s)->map->count()->toArray(),
        ];

        $perPage   = $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'summary' => $summary,
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // B. Shortlisted Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Shortlisted report — candidates currently at "Shortlisted" status.
     *
     * Filters: date_from, date_to (shortlisted_at from status history),
     *          job_opening_id, search, per_page
     */
    public function shortlisted(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'job_opening_id' => 'nullable|integer|exists:job_openings,id',
            'search'         => 'nullable|string|max:100',
            'per_page'       => 'nullable|integer|min:5|max:100',
        ]);

        // Get shortlisted timestamp from status history
        $shortlistedAtByCandidate = CandidateStatusHistory::where('to_status', 'Shortlisted')
            ->selectRaw('candidate_id, MIN(changed_at) as shortlisted_at')
            ->groupBy('candidate_id')
            ->pluck('shortlisted_at', 'candidate_id');

        $query = Candidate::with(['jobOpening:id,title,job_code'])
            ->where('status', 'Shortlisted')
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->filled('search'),         fn($q) => $q->search($request->search))
            ->orderBy('created_at', 'desc');

        // Filter by shortlisted date range if provided
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $eligibleIds = $shortlistedAtByCandidate->filter(function ($at, $id) use ($request) {
                $dt = Carbon::parse($at);
                if ($request->filled('date_from') && $dt->lt(Carbon::parse($request->date_from)->startOfDay())) return false;
                if ($request->filled('date_to')   && $dt->gt(Carbon::parse($request->date_to)->endOfDay()))   return false;
                return true;
            })->keys();
            $query->whereIn('id', $eligibleIds);
        }

        $perPage   = $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        // Attach shortlisted_at to each item
        $items = collect($paginated->items())->map(function ($c) use ($shortlistedAtByCandidate) {
            $c->shortlisted_at = $shortlistedAtByCandidate->get($c->id);
            return $c;
        });

        return response()->json([
            'summary' => ['total' => $paginated->total()],
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // C. Interviews Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Interviews report — paginated interview list with filters and summary.
     *
     * Filters: date_from, date_to (scheduled_at), job_opening_id,
     *          interviewer_employee_id, status, search, per_page
     */
    public function interviews(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'              => 'nullable|date',
            'date_to'                => 'nullable|date|after_or_equal:date_from',
            'job_opening_id'         => 'nullable|integer|exists:job_openings,id',
            'interviewer_employee_id'=> 'nullable|integer|exists:employees,id',
            'status'                 => 'nullable|string|in:Scheduled,Completed,Cancelled,No Show',
            'search'                 => 'nullable|string|max:100',
            'per_page'               => 'nullable|integer|min:5|max:100',
        ]);

        $query = Interview::with([
            'candidate:id,first_name,last_name,candidate_code,email',
            'jobOpening:id,title,job_code',
            'interviewer:id,first_name,last_name,employee_code',
        ])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('scheduled_at', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn($q) => $q->whereDate('scheduled_at', '<=', $request->date_to))
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->filled('interviewer_employee_id'), fn($q) => $q->where('interviewer_employee_id', $request->interviewer_employee_id))
            ->when($request->filled('status'),    fn($q) => $q->where('status', $request->status))
            ->when($request->filled('search'),    fn($q) => $q->search($request->search))
            ->orderBy('scheduled_at', 'desc');

        // Summary counts
        $allStatuses = (clone $query)->pluck('status');
        $summary = [
            'total'       => $allStatuses->count(),
            'by_status'   => $allStatuses->groupBy(fn($s) => $s)->map->count()->toArray(),
        ];

        $perPage   = $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'summary' => $summary,
            'data'    => $paginated->items(),
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // D. Selected / Hired Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Selected report — candidates who reached "Hired" status.
     *
     * Filters: date_from, date_to (hired_at from status history),
     *          job_opening_id, search, per_page
     */
    public function selected(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'job_opening_id' => 'nullable|integer|exists:job_openings,id',
            'search'         => 'nullable|string|max:100',
            'per_page'       => 'nullable|integer|min:5|max:100',
        ]);

        // Get hired timestamp from status history for each candidate
        $hiredAtByCandidate = CandidateStatusHistory::where('to_status', 'Hired')
            ->selectRaw('candidate_id, MIN(changed_at) as hired_at')
            ->groupBy('candidate_id')
            ->pluck('hired_at', 'candidate_id');

        $query = Candidate::with(['jobOpening:id,title,job_code'])
            ->where('status', 'Hired')
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->filled('search'),         fn($q) => $q->search($request->search))
            ->orderBy('created_at', 'desc');

        // Filter by hired date range
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $eligibleIds = $hiredAtByCandidate->filter(function ($at, $id) use ($request) {
                $dt = Carbon::parse($at);
                if ($request->filled('date_from') && $dt->lt(Carbon::parse($request->date_from)->startOfDay())) return false;
                if ($request->filled('date_to')   && $dt->gt(Carbon::parse($request->date_to)->endOfDay()))   return false;
                return true;
            })->keys();
            $query->whereIn('id', $eligibleIds);
        }

        $perPage   = $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        $items = collect($paginated->items())->map(function ($c) use ($hiredAtByCandidate) {
            $c->hired_at = $hiredAtByCandidate->get($c->id);
            return $c;
        });

        return response()->json([
            'summary' => ['total' => $paginated->total()],
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // E. Hiring Cost Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Hiring cost report — NOTE: no dedicated cost-tracking model exists.
     *
     * This report exposes accepted offer salaries as the only available
     * monetary metric. It does NOT represent actual hiring cost (recruiter
     * fees, advertising, etc.) as no such data is stored in this system.
     *
     * Filters: date_from, date_to (offer_date), job_opening_id, per_page
     */
    public function hiringCost(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'job_opening_id' => 'nullable|integer|exists:job_openings,id',
            'per_page'       => 'nullable|integer|min:5|max:100',
        ]);

        $query = OfferLetter::with([
            'candidate:id,first_name,last_name,candidate_code',
            'jobOpening:id,title,job_code',
            'department:id,name',
        ])
            ->where('offer_status', 'Accepted')
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('offer_date', '>=', $request->date_from))
            ->when($request->filled('date_to'),   fn($q) => $q->whereDate('offer_date', '<=', $request->date_to))
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->orderBy('offer_date', 'desc');

        $allOffers = (clone $query)->get(['salary_amount', 'salary_currency', 'salary_frequency']);
        $totalSalary = $allOffers->sum('salary_amount');

        $perPage   = $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        return response()->json([
            'disclaimer' => 'This system does not track actual hiring costs (advertising, recruiter fees, etc.). '
                          . 'The figures below reflect accepted offer salaries only, which is the only monetary '
                          . 'data available in the current schema.',
            'summary' => [
                'total_accepted_offers'   => $allOffers->count(),
                'total_offered_salary'    => round((float) $totalSalary, 2),
                'avg_offered_salary'      => $allOffers->count() > 0
                    ? round((float) ($totalSalary / $allOffers->count()), 2)
                    : 0,
            ],
            'data' => $paginated->items(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // F. Time-to-Hire Report
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Time-to-hire report.
     *
     * Calculation:
     *   start = candidates.created_at   (application/profile creation date)
     *   end   = MIN(candidate_status_histories.changed_at) WHERE to_status = 'Hired'
     *   days  = ceil(end - start) in calendar days
     *
     * Only candidates who reached "Hired" status appear here.
     *
     * Filters: date_from, date_to (hired_at), job_opening_id, search, per_page
     */
    public function timeToHire(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'      => 'nullable|date',
            'date_to'        => 'nullable|date|after_or_equal:date_from',
            'job_opening_id' => 'nullable|integer|exists:job_openings,id',
            'search'         => 'nullable|string|max:100',
            'per_page'       => 'nullable|integer|min:5|max:100',
        ]);

        // Fetch hired timestamps from status history
        $hiredAtByCandidate = CandidateStatusHistory::where('to_status', 'Hired')
            ->selectRaw('candidate_id, MIN(changed_at) as hired_at')
            ->groupBy('candidate_id')
            ->pluck('hired_at', 'candidate_id');

        // Narrow down to candidates who ARE in 'Hired' status and have a history entry
        $query = Candidate::with(['jobOpening:id,title,job_code'])
            ->where('status', 'Hired')
            ->whereIn('id', $hiredAtByCandidate->keys())
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->when($request->filled('search'),         fn($q) => $q->search($request->search))
            ->orderBy('created_at', 'desc');

        // Filter by hired date range
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $eligibleIds = $hiredAtByCandidate->filter(function ($at, $id) use ($request) {
                $dt = Carbon::parse($at);
                if ($request->filled('date_from') && $dt->lt(Carbon::parse($request->date_from)->startOfDay())) return false;
                if ($request->filled('date_to')   && $dt->gt(Carbon::parse($request->date_to)->endOfDay()))   return false;
                return true;
            })->keys();
            $query->whereIn('id', $eligibleIds);
        }

        $perPage   = $request->input('per_page', 15);
        $paginated = $query->paginate($perPage);

        // Compute days_to_hire for each paginated item
        $items = collect($paginated->items())->map(function ($c) use ($hiredAtByCandidate) {
            $hiredAt = $hiredAtByCandidate->get($c->id);
            $c->hired_at      = $hiredAt;
            $c->days_to_hire  = $hiredAt
                ? (int) ceil(Carbon::parse($c->created_at)->diffInDays(Carbon::parse($hiredAt), true))
                : null;
            return $c;
        });

        // Aggregate stats from ALL matching records (not just current page)
        $allIds   = (clone $query)->pluck('id', 'created_at');
        $allItems = Candidate::whereIn('id', $hiredAtByCandidate->keys())
            ->where('status', 'Hired')
            ->when($request->filled('job_opening_id'), fn($q) => $q->where('job_opening_id', $request->job_opening_id))
            ->pluck('created_at', 'id');

        $allDays = $allItems->map(function ($createdAt, $id) use ($hiredAtByCandidate) {
            $hiredAt = $hiredAtByCandidate->get($id);
            return $hiredAt
                ? (int) ceil(Carbon::parse($createdAt)->diffInDays(Carbon::parse($hiredAt), true))
                : null;
        })->filter()->values();

        return response()->json([
            'calculation_note' => 'Time-to-hire = calendar days from candidate creation (application date) to '
                                . 'the date the candidate first reached "Hired" status in the pipeline.',
            'summary' => [
                'total_hired'       => $paginated->total(),
                'avg_days_to_hire'  => $allDays->count() > 0 ? round($allDays->avg(), 1) : null,
                'min_days_to_hire'  => $allDays->count() > 0 ? $allDays->min() : null,
                'max_days_to_hire'  => $allDays->count() > 0 ? $allDays->max() : null,
            ],
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }
}
