<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        $query = Timesheet::with(['employee:id,first_name,last_name', 'project:id,name', 'task:id,name']);

        $user = $request->user();
        // Secure by default: Employees only see their own timesheets unless they have admin permissions.
        if (!$user->hasRole('Admin') && !$user->hasRole('HR Admin')) {
            $query->where('employee_id', $user->employee_id);
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return response()->json(
            $query->orderByDesc('date')->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['employee_id'] = $request->user()->employee_id ?? $data['employee_id'];
        
        // Validate max 24 hours per day
        $existingHours = Timesheet::where('employee_id', $data['employee_id'])
            ->where('date', $data['date'])
            ->sum('hours');
            
        if (($existingHours + $data['hours']) > 24) {
            return response()->json(['message' => "Total hours for {$data['date']} exceed 24."], 422);
        }

        if (isset($data['billable_hours']) || isset($data['non_billable_hours'])) {
            $billable = $data['billable_hours'] ?? $data['hours'];
            $nonBillable = $data['non_billable_hours'] ?? 0;
            if (abs(($billable + $nonBillable) - $data['hours']) > 0.01) {
                return response()->json(['message' => 'Billable and non-billable hours must equal total hours.'], 422);
            }
        }

        $data['status'] = 'Draft';

        $timesheet = Timesheet::create($data);

        return response()->json($timesheet, 201);
    }

    /**
     * Accepts an array of daily entries for a week and creates them together,
     * used by WeeklyTimesheet.jsx.
     */
    public function storeWeekly(Request $request)
    {
        $payload = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.project_id' => ['required', 'exists:projects,id'],
            'entries.*.task_id' => ['nullable', 'exists:tasks,id'],
            'entries.*.date' => ['required', 'date'],
            'entries.*.hours' => ['required', 'numeric', 'min:0', 'max:24'],
            'entries.*.billable_hours' => ['nullable', 'numeric', 'min:0'],
            'entries.*.non_billable_hours' => ['nullable', 'numeric', 'min:0'],
            'entries.*.client' => ['nullable', 'string'],
        ]);

        $employeeId = $request->user()->employee_id;
        
        if (!$employeeId) {
            return response()->json(['message' => 'User is not linked to an employee record.'], 403);
        }

        // Validate max 24 hours per day total across all entries and existing db records
        $dateTotals = [];
        foreach ($payload['entries'] as $entry) {
            $date = $entry['date'];
            $dateTotals[$date] = ($dateTotals[$date] ?? 0) + $entry['hours'];
        }

        foreach ($dateTotals as $date => $total) {
            $existingHours = Timesheet::where('employee_id', $employeeId)->where('date', $date)->sum('hours');
            if (($existingHours + $total) > 24) {
                return response()->json(['message' => "Total hours for {$date} exceed 24."], 422);
            }
        }

        $created = collect($payload['entries'])->map(function ($entry) use ($employeeId) {
            $billable = $entry['billable_hours'] ?? $entry['hours'];
            $nonBillable = $entry['non_billable_hours'] ?? 0;
            
            // Adjust if they don't match, or we could throw a validation error. 
            // We'll trust the provided total hours and adjust billable if needed.
            if (abs(($billable + $nonBillable) - $entry['hours']) > 0.01) {
                $billable = $entry['hours'];
                $nonBillable = 0;
            }

            return Timesheet::updateOrCreate([
                'employee_id' => $employeeId,
                'project_id' => $entry['project_id'],
                'task_id' => $entry['task_id'] ?? null,
                'date' => $entry['date'],
            ], [
                'hours' => $entry['hours'],
                'billable_hours' => $billable,
                'non_billable_hours' => $nonBillable,
                'client' => $entry['client'] ?? null,
                'status' => 'Draft',
            ]);
        });

        return response()->json($created, 201);
    }

    public function show(Timesheet $timesheet)
    {
        return response()->json($timesheet->load('employee:id,first_name,last_name', 'project:id,name', 'task:id,name'));
    }

    public function update(Request $request, Timesheet $timesheet)
    {
        if (in_array($timesheet->status, ['Approved', 'Submitted'])) {
            return response()->json(['message' => 'Cannot modify a timesheet that is already submitted or approved.'], 403);
        }

        $data = $this->validated($request, sometimes: true);
        
        // Remove employee_id from update if present to prevent reassignment
        unset($data['employee_id']);

        $newHours = array_key_exists('hours', $data) ? (float) $data['hours'] : $timesheet->hours;
        
        // Validate max 24 hours per day
        if (array_key_exists('hours', $data) && $newHours !== $timesheet->hours) {
            $existingHours = Timesheet::where('employee_id', $timesheet->employee_id)
                ->where('date', $timesheet->date)
                ->where('id', '!=', $timesheet->id)
                ->sum('hours');
                
            if (($existingHours + $newHours) > 24) {
                return response()->json(['message' => "Total hours for {$timesheet->date} exceed 24."], 422);
            }
        }
        
        // Validate billable/non-billable hours sum
        $billable = array_key_exists('billable_hours', $data) ? (float) $data['billable_hours'] : $timesheet->billable_hours;
        $nonBillable = array_key_exists('non_billable_hours', $data) ? (float) $data['non_billable_hours'] : $timesheet->non_billable_hours;
        
        if (abs(($billable + $nonBillable) - $newHours) > 0.01) {
            return response()->json(['message' => 'Billable and non-billable hours must equal total hours.'], 422);
        }

        $timesheet->update($data);

        return response()->json($timesheet);
    }

    public function destroy(Timesheet $timesheet)
    {
        if (in_array($timesheet->status, ['Approved', 'Submitted'])) {
            return response()->json(['message' => 'Cannot delete a timesheet that is already submitted or approved.'], 403);
        }

        $timesheet->delete();

        return response()->json(null, 204);
    }

    public function submit(Timesheet $timesheet)
    {
        if ($timesheet->status !== 'Draft' && $timesheet->status !== 'Rejected') {
            return response()->json(['message' => 'Only Draft or Rejected timesheets can be submitted.'], 422);
        }

        $timesheet->update(['status' => 'Submitted']);
        return response()->json($timesheet);
    }

    public function approve(Timesheet $timesheet)
    {
        if ($timesheet->status !== 'Submitted') {
            return response()->json(['message' => 'Only Submitted timesheets can be approved.'], 422);
        }

        $timesheet->update(['status' => 'Approved']);
        return response()->json($timesheet);
    }

    public function reject(Request $request, Timesheet $timesheet)
    {
        if ($timesheet->status !== 'Submitted' && $timesheet->status !== 'Approved') {
            return response()->json(['message' => 'Invalid status transition.'], 422);
        }

        $request->validate(['notes' => ['nullable', 'string']]);

        $timesheet->update([
            'status' => 'Rejected',
            'notes' => $request->input('notes', $timesheet->notes),
        ]);

        return response()->json($timesheet);
    }

    private function validated(Request $request, bool $sometimes = false): array
    {
        return $request->validate([
            'employee_id' => $sometimes ? ['sometimes', 'exists:employees,id'] : ['required', 'exists:employees,id'],
            'project_id' => $sometimes ? ['sometimes', 'exists:projects,id'] : ['required', 'exists:projects,id'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'date' => $sometimes ? ['sometimes', 'date'] : ['required', 'date'],
            'hours' => $sometimes ? ['sometimes', 'numeric', 'min:0', 'max:24'] : ['required', 'numeric', 'min:0', 'max:24'],
            'billable_hours' => ['nullable', 'numeric', 'min:0'],
            'non_billable_hours' => ['nullable', 'numeric', 'min:0'],
            'client' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
