<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs with authorization and server-side filtering.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('audit.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = AuditLog::with([
            'user:id,name,email',
            'employee:id,first_name,last_name,employee_code',
        ]);

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $logs = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($logs);
    }

    /**
     * Display the specified audit log record.
     */
    public function show(Request $request, AuditLog $auditLog)
    {
        $user = $request->user();

        if (!$user->can('audit.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $auditLog->load([
            'user:id,name,email',
            'employee:id,first_name,last_name,employee_code',
        ]);

        return response()->json($auditLog);
    }
}
