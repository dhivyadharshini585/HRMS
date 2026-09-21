<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request)
    {
        $query = Employee::with(['department', 'designation']);

        // Scope Manager access to their own team
        $user = $request->user();
        if ($user && $user->hasRole('Manager') && !$user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive', 'Finance/Payroll Admin'])) {
            $managerEmp = $user->employee;
            if ($managerEmp) {
                $teamEmployeeIds = Employee::where('manager_id', $managerEmp->id)
                    ->pluck('id')
                    ->push($managerEmp->id);
                $query->whereIn('id', $teamEmployeeIds);
            } else {
                // If Manager role but no employee record, can't see anyone
                $query->where('id', -1);
            }
        }

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
        $user = $request->user();

        $createCredentials = filter_var($request->input('create_credentials', false), FILTER_VALIDATE_BOOLEAN);

        if ($createCredentials) {
            if (!$user || !$user->hasRole('Super Admin')) {
                return response()->json([
                    'message' => 'Unauthorized. Only Super Admin can create login credentials.'
                ], 403);
            }

            if (User::where('email', $validated['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => ['A user account with this email address already exists.'],
                ]);
            }
        }

        $employee = DB::transaction(function () use ($validated, $request, $createCredentials) {
            if ($createCredentials) {
                $userAccount = User::create([
                    'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                    'email' => $validated['email'],
                    'password' => Hash::make($request->input('password')),
                ]);
                $userAccount->assignRole('Employee');
                $validated['user_id'] = $userAccount->id;
            }

            unset($validated['create_credentials'], $validated['password'], $validated['password_confirmation']);

            $validated['employee_code'] = Employee::generateEmployeeCode();

            return Employee::create($validated);
        });

        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $employee->load(['department', 'designation', 'user'])
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
        $isSelf = $user->employee?->id === $employee->id;

        if (!$user->hasPermissionTo('employees.view') && !$isSelf) {
            return response()->json(['message' => 'Unauthorized to view this employee profile.'], 403);
        }

        // Additional scoping for Managers: can only view themselves or direct reports
        if ($user->hasRole('Manager') && !$user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive', 'Finance/Payroll Admin'])) {
            $isDirectReport = $user->employee?->id === $employee->manager_id;
            if (!$isSelf && !$isDirectReport) {
                return response()->json(['message' => 'Unauthorized to view this employee profile.'], 403);
            }
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

    /**
     * Create login credentials for an existing employee.
     */
    public function createCredentials(Request $request, Employee $employee)
    {
        $authUser = $request->user();

        if (!$authUser || !$authUser->hasRole('Super Admin')) {
            return response()->json([
                'message' => 'Unauthorized. Only Super Admin can create login credentials.'
            ], 403);
        }

        if ($employee->user_id !== null) {
            return response()->json([
                'message' => 'This employee already has a login account.'
            ], 400);
        }

        if (User::where('email', $employee->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A user account with this email address already exists.'],
            ]);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $updatedEmployee = DB::transaction(function () use ($request, $employee) {
            $newUser = User::create([
                'name' => trim($employee->first_name . ' ' . $employee->last_name),
                'email' => $employee->email,
                'password' => Hash::make($request->input('password')),
            ]);
            $newUser->assignRole('Employee');

            $employee->update(['user_id' => $newUser->id]);
            return $employee;
        });

        return response()->json([
            'message' => 'Login credentials created successfully',
            'data' => $updatedEmployee->fresh()->load(['department', 'designation', 'user'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Change password for an existing employee's linked user account.
     */
    public function changePassword(Request $request, Employee $employee)
    {
        $authUser = $request->user();

        if (!$authUser || !$authUser->hasRole('Super Admin')) {
            return response()->json([
                'message' => 'Unauthorized. Only Super Admin can change employee passwords.'
            ], 403);
        }

        if ($employee->user_id === null || !$employee->user) {
            return response()->json([
                'message' => 'This employee does not have a linked user account.'
            ], 400);
        }

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $employee->user;
        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Revoke all existing Sanctum tokens for the user
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Employee password changed successfully and active sessions were revoked.',
            'data' => $employee->fresh()->load(['department', 'designation', 'user'])
        ]);
    }
}
