<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request)
    {
        $query = Employee::with(['department', 'designation']);

        // Search by name, email, or code
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('first_name', 'like', "%{$searchTerm}%")
                  ->orWhere('last_name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%")
                  ->orWhere('employee_code', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by department
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        // Filter by designation
        if ($request->filled('designation_id')) {
            $query->where('designation_id', $request->designation_id);
        }

        // Filter by employment status
        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }

        $employees = $query->latest()->paginate($request->per_page ?? 10);

        return response()->json($employees);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(StoreEmployeeRequest $request)
    {
        $validated = $request->validated();
        
        // Auto-generate employee code
        $validated['employee_code'] = Employee::generateEmployeeCode();

        $employee = Employee::create($validated);

        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $employee->load(['department', 'designation'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Get the authenticated user's own employee profile.
     */
    public function meProfile(Request $request)
    {
        $user = $request->user();
        $employee = $user->employee;

        if (!$employee) {
            return response()->json(['message' => 'No employee profile associated with this user.'], 404);
        }

        $employee->load([
            'department',
            'designation',
            'shift',
            'user',
            'manager.designation',
            'manager.department',
            'employmentHistories' => function ($q) {
                $q->orderBy('start_date', 'desc');
            }
        ]);

        $data = $employee->toArray();
        $data['current_shift'] = $employee->currentShift();

        return response()->json($data);
    }

    /**
     * Display the specified employee.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $employee = Employee::with([
            'department',
            'designation',
            'shift',
            'user',
            'manager.designation',
            'manager.department',
            'employmentHistories' => function ($q) {
                $q->orderBy('start_date', 'desc');
            }
        ])->findOrFail($id);

        // RBAC: Must have 'employees.view' OR be viewing their own employee record
        if (!$user->hasPermissionTo('employees.view') && $user->employee?->id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized to view this employee profile.'], 403);
        }

        $data = $employee->toArray();
        $data['current_shift'] = $employee->currentShift();

        return response()->json($data);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(UpdateEmployeeRequest $request, string $id)
    {
        $employee = Employee::findOrFail($id);
        
        $employee->update($request->validated());

        return response()->json([
            'message' => 'Employee updated successfully',
            'data' => $employee->load(['department', 'designation', 'manager'])
        ]);
    }

    /**
     * Remove the specified employee from storage (soft delete).
     */
    public function destroy(string $id)
    {
        $employee = Employee::findOrFail($id);
        
        // Update status to terminated before soft deleting
        $employee->update(['employment_status' => 'Terminated']);
        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully'
        ]);
    }

    /**
     * Get all departments for dropdowns.
     */
    public function getDepartments()
    {
        $departments = Department::select('id', 'name')->orderBy('name')->get();
        return response()->json($departments);
    }

    /**
     * Get all designations for dropdowns.
     */
    public function getDesignations()
    {
        $designations = Designation::select('id', 'title')->orderBy('title')->get();
        return response()->json($designations);
    }
}
