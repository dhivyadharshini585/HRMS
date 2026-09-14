<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('manager:id,first_name,last_name');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate($request->input('per_page', 20))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'status' => ['nullable', Rule::in(['Active', 'On Hold', 'Completed', 'Cancelled'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if (empty($data['start_date']) && !empty($data['end_date'])) {
            return response()->json(['message' => 'start_date is required when end_date is provided.'], 422);
        }

        $project = Project::create($data);

        return response()->json($project, 201);
    }

    public function show(Project $project)
    {
        return response()->json($project->load('manager:id,first_name,last_name', 'tasks'));
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'status' => ['sometimes', Rule::in(['Active', 'On Hold', 'Completed', 'Cancelled'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        if (empty($data['start_date']) && empty($project->start_date) && !empty($data['end_date'])) {
            return response()->json(['message' => 'start_date is required when end_date is provided.'], 422);
        }

        $project->update($data);

        return response()->json($project);
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->json(null, 204);
    }

    // --- Tasks nested under a project ---

    public function storeTask(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['To Do', 'In Progress', 'Blocked', 'Done'])],
        ]);

        $task = $project->tasks()->create($data);

        return response()->json($task, 201);
    }

    public function updateTask(Request $request, Project $project, Task $task)
    {
        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Task does not belong to this project.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['To Do', 'In Progress', 'Blocked', 'Done'])],
        ]);

        $task->update($data);

        return response()->json($task);
    }

    public function destroyTask(Project $project, Task $task)
    {
        if ($task->project_id !== $project->id) {
            return response()->json(['message' => 'Task does not belong to this project.'], 404);
        }

        $task->delete();

        return response()->json(null, 204);
    }

    /**
     * Utilization summary powering ProjectUtilization.jsx charts:
     * total / billable / non-billable hours per project (optionally filtered by date range).
     */
    public function utilization(Request $request)
    {
        $query = DB::table('timesheets')
            ->join('projects', 'projects.id', '=', 'timesheets.project_id')
            ->select(
                'projects.id as project_id',
                'projects.name as name',
                DB::raw('SUM(timesheets.hours) as hours'),
                DB::raw('SUM(timesheets.billable_hours) as billable_hours'),
                DB::raw('SUM(timesheets.non_billable_hours) as non_billable_hours')
            )
            ->groupBy('projects.id', 'projects.name');

        if ($request->filled('from')) {
            $query->where('timesheets.date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('timesheets.date', '<=', $request->input('to'));
        }

        $projects = $query->get();

        $billable = $projects->sum('billable_hours');
        $non_billable = $projects->sum('non_billable_hours');

        return response()->json([
            'billable' => $billable,
            'non_billable' => $non_billable,
            'projects' => $projects
        ]);
    }
}
