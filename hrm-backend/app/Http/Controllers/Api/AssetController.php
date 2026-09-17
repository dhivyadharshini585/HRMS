<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index(Request $request)
    {
        $query = Asset::with('currentAssignment.employee:id,first_name,last_name');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('asset_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return response()->json(
            $query->orderByDesc('created_at')->paginate($perPage)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'asset_id' => ['required', 'string', 'max:50', 'unique:assets,asset_id'],
            'type' => ['required', Rule::in(['Laptop', 'Desktop', 'Monitor', 'Keyboard', 'Mouse', 'Mobile', 'ID Card', 'Software License'])],
            'name' => ['required', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'condition' => ['nullable', Rule::in(['New', 'Good', 'Fair', 'Damaged'])],
        ]);

        $asset = Asset::create($data + ['status' => 'Unassigned']);

        return response()->json($asset, 201);
    }

    public function show(Asset $asset)
    {
        return response()->json($asset->load('assignments.employee:id,first_name,last_name'));
    }

    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['Laptop', 'Desktop', 'Monitor', 'Keyboard', 'Mouse', 'Mobile', 'ID Card', 'Software License'])],
            'name' => ['sometimes', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'condition' => ['sometimes', Rule::in(['New', 'Good', 'Fair', 'Damaged'])],
            'status' => ['sometimes', Rule::in(['Unassigned', 'Assigned', 'In Maintenance', 'Retired'])],
        ]);

        $asset->update($data);

        return response()->json($asset);
    }

    public function destroy(Asset $asset)
    {
        $asset->delete();

        return response()->json(null, 204);
    }

    /**
     * Assign an asset to an employee. Fails if the asset is already assigned.
     */
    public function assign(Request $request, Asset $asset)
    {
        if ($asset->status !== 'Unassigned') {
            return response()->json(['message' => "Cannot assign asset. Current status is {$asset->status}."], 422);
        }

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'assigned_date' => ['required', 'date'],
            'condition_at_assignment' => ['nullable', Rule::in(['New', 'Good', 'Fair', 'Damaged'])],
            'notes' => ['nullable', 'string'],
        ]);

        $assignment = AssetAssignment::create($data + [
            'asset_id' => $asset->id,
            'status' => 'Active',
        ]);

        $asset->update(['status' => 'Assigned']);

        return response()->json($assignment->load('employee:id,first_name,last_name'), 201);
    }

    /**
     * Return an asset from its currently active assignment.
     */
    public function returnAsset(Request $request, Asset $asset)
    {
        $assignment = $asset->currentAssignment()->first();

        if (! $assignment) {
            return response()->json(['message' => 'This asset has no active assignment.'], 422);
        }
        if ($asset->status !== 'Assigned') {
            return response()->json(['message' => 'Asset is already marked as returned or unavailable.'], 422);
        }

        $data = $request->validate([
            'returned_date' => ['required', 'date'],
            'condition_at_return' => ['required', Rule::in(['New', 'Good', 'Fair', 'Damaged'])],
            'notes' => ['nullable', 'string'],
        ]);

        if (strtotime($data['returned_date']) < strtotime($assignment->assigned_date)) {
            return response()->json(['message' => 'Returned date cannot be earlier than the assigned date.'], 422);
        }

        $assignment->update($data + ['status' => 'Returned']);
        $asset->update(['status' => 'Unassigned', 'condition' => $data['condition_at_return']]);

        return response()->json($assignment);
    }

    public function assignments(Request $request)
    {
        $query = AssetAssignment::with(['asset', 'employee:id,first_name,last_name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return response()->json(
            $query->orderByDesc('assigned_date')->paginate($perPage)
        );
    }
}
