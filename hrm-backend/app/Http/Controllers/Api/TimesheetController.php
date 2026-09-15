<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Timesheet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class TimesheetController extends Controller
{
    private function getEmployeeId(Request $request): ?int
    {
        $user = $request->user();
        return $user->employee?->id ?? $user->employee_id;
    }

    public function index(Request $request)
    {
        $query = Timesheet::with(['employee:id,first_name,last_name', 'project:id,name', 'task:id,name']);

        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if ($user->hasRole('Super Admin') || $user->hasRole('HR Admin') || $user->hasRole('Finance/Payroll Admin')) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
        } elseif ($user->hasRole('Manager')) {
            $query->where(function ($q) use ($employeeId) {
                $q->where('employee_id', $employeeId)
                  ->orWhereHas('employee', function ($eq) use ($employeeId) {
                      $eq->where('manager_id', $employeeId);
                  });
            });
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
        } else {
            // Employee / HR Executive / others: strictly own timesheets
            $query->where('employee_id', $employeeId);
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
        $employeeId = $this->getEmployeeId($request);
        $data['employee_id'] = $employeeId ?? $data['employee_id'];
        
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

        $employeeId = $this->getEmployeeId($request);
        
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

    public function show(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if (!$user->hasRole('Super Admin') && !$user->hasRole('HR Admin') && !$user->hasRole('Finance/Payroll Admin')) {
            if ($user->hasRole('Manager')) {
                $isOwn = $timesheet->employee_id == $employeeId;
                $isDirectReport = $timesheet->employee && $timesheet->employee->manager_id == $employeeId;
                if (!$isOwn && !$isDirectReport) {
                    return response()->json(['message' => 'Unauthorized timesheet access.'], 403);
                }
            } else {
                if ($timesheet->employee_id != $employeeId) {
                    return response()->json(['message' => 'Unauthorized timesheet access.'], 403);
                }
            }
        }

        return response()->json($timesheet->load('employee:id,first_name,last_name', 'project:id,name', 'task:id,name'));
    }

    public function update(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if (!$user->hasRole('Super Admin') && !$user->hasRole('HR Admin')) {
            if ($timesheet->employee_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized to modify this timesheet.'], 403);
            }
        }

        if (in_array($timesheet->status, ['Approved', 'Submitted'])) {
            return response()->json(['message' => 'Cannot modify a timesheet that is already submitted or approved.'], 403);
        }

        $data = $this->validated($request, sometimes: true);
        
        unset($data['employee_id']);

        $timesheet->update($data);

        return response()->json($timesheet);
    }

    public function destroy(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if (!$user->hasRole('Super Admin') && !$user->hasRole('HR Admin')) {
            if ($timesheet->employee_id != $employeeId) {
                return response()->json(['message' => 'Unauthorized to delete this timesheet.'], 403);
            }
        }

        if (in_array($timesheet->status, ['Approved', 'Submitted'])) {
            return response()->json(['message' => 'Cannot delete a timesheet that is already submitted or approved.'], 403);
        }

        $timesheet->delete();

        return response()->json(null, 204);
    }

    public function submit(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if (!$user->hasRole('Super Admin') && !$user->hasRole('HR Admin') && $timesheet->employee_id != $employeeId) {
            return response()->json(['message' => 'Unauthorized to submit this timesheet.'], 403);
        }

        if ($timesheet->status !== 'Draft' && $timesheet->status !== 'Rejected') {
            return response()->json(['message' => 'Only Draft or Rejected timesheets can be submitted.'], 422);
        }

        $timesheet->update(['status' => 'Submitted']);
        return response()->json($timesheet);
    }

    public function approve(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if (!$user->hasRole('Super Admin') && !$user->hasRole('HR Admin')) {
            if ($user->hasRole('Manager')) {
                if (!$timesheet->employee || $timesheet->employee->manager_id != $employeeId) {
                    return response()->json(['message' => 'Managers can only approve direct reports timesheets.'], 403);
                }
            }
        }

        if ($timesheet->status !== 'Submitted') {
            return response()->json(['message' => 'Only Submitted timesheets can be approved.'], 422);
        }

        $timesheet->update(['status' => 'Approved']);
        return response()->json($timesheet);
    }

    public function reject(Request $request, Timesheet $timesheet)
    {
        $user = $request->user();
        $employeeId = $this->getEmployeeId($request);

        if (!$user->hasRole('Super Admin') && !$user->hasRole('HR Admin')) {
            if ($user->hasRole('Manager')) {
                if (!$timesheet->employee || $timesheet->employee->manager_id != $employeeId) {
                    return response()->json(['message' => 'Managers can only reject direct reports timesheets.'], 403);
                }
            }
        }

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
