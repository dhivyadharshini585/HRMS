<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private const STATUSES = ['Open', 'Assigned', 'In Progress', 'Waiting', 'Resolved', 'Closed'];

    public function index(Request $request)
    {
        $query = SupportTicket::with(['employee:id,first_name,last_name', 'assignee:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return response()->json(
            $query->orderByRaw("FIELD(priority, 'Critical','High','Medium','Low')")
                ->orderByDesc('created_at')
                ->paginate($perPage)
        );
    }

    /**
     * Counts per status, used to drive the status filter tabs in TicketList.jsx.
     */
    public function statusCounts()
    {
        $counts = SupportTicket::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json(
            collect(self::STATUSES)->mapWithKeys(fn ($status) => [$status => $counts[$status] ?? 0])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'problem' => ['required', 'string', 'min:5', 'max:500'],
            'category' => ['nullable', Rule::in(['IT Support', 'Hardware', 'Software', 'Network', 'Access', 'Other'])],
            'priority' => ['nullable', Rule::in(['Low', 'Medium', 'High', 'Critical'])],
        ]);

        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'User must be linked to an employee.'], 403);
        }

        $ticket = SupportTicket::create($data + [
            'employee_id' => $employee->id,
            'ticket_number' => SupportTicket::generateTicketNumber(),
            'status' => 'Open',
        ]);

        return response()->json($ticket, 201);
    }

    public function show(SupportTicket $supportTicket)
    {
        return response()->json($supportTicket->load('employee:id,first_name,last_name', 'assignee:id,name'));
    }

    public function update(Request $request, SupportTicket $supportTicket)
    {
        $data = $request->validate([
            'problem' => ['sometimes', 'string', 'max:500'],
            'category' => ['sometimes', Rule::in(['IT Support', 'Hardware', 'Software', 'Network', 'Access', 'Other'])],
            'priority' => ['sometimes', Rule::in(['Low', 'Medium', 'High', 'Critical'])],
        ]);

        $supportTicket->update($data);

        return response()->json($supportTicket);
    }

    /**
     * Dedicated status-change endpoint — powers the status dropdown in
     * TicketList.jsx instead of a drag-and-drop board.
     */
    public function updateStatus(Request $request, SupportTicket $supportTicket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
            'assigned_to' => ['required_if:status,Assigned', 'nullable', 'exists:users,id'],
            'resolution_notes' => ['nullable', 'string'],
        ]);

        $supportTicket->update($data);

        return response()->json($supportTicket);
    }

    public function destroy(SupportTicket $supportTicket)
    {
        $supportTicket->delete();

        return response()->json(null, 204);
    }
}
