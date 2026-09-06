<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Display a listing of authorized documents.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('documents.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $query = EmployeeDocument::with([
            'employee:id,first_name,last_name,employee_code',
            'uploader:id,name',
        ]);

        // Enforce role-based access scope
        if ($user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            // Administrative users can filter by any employee_id
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->input('employee_id'));
            }
        } elseif ($user->hasRole('Manager') && $user->employee) {
            // Manager can access direct reports and own documents
            $allowedEmployeeIds = $user->employee->directReports()->pluck('id')->push($user->employee->id)->toArray();
            
            if ($request->filled('employee_id')) {
                $requestedId = (int) $request->input('employee_id');
                if (!in_array($requestedId, $allowedEmployeeIds, true)) {
                    return response()->json(['message' => 'You are not authorized to view documents for this employee.'], 403);
                }
                $query->where('employee_id', $requestedId);
            } else {
                $query->whereIn('employee_id', $allowedEmployeeIds);
            }
        } else {
            // Employee and other non-admin roles: strictly force to own employee record
            $userEmployeeId = $user->employee?->id;
            if (!$userEmployeeId) {
                return response()->json([], 200);
            }
            $query->where('employee_id', $userEmployeeId);
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('document_category', $request->input('category'));
        }

        $documents = $query->orderByDesc('created_at')->get();

        return response()->json($documents);
    }

    /**
     * Store a newly uploaded document.
     */
    public function store(StoreDocumentRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        $file = $request->file('file');

        // Store file in private non-public disk under 'documents' directory
        $filePath = $file->store('documents', 'local');

        $document = EmployeeDocument::create([
            'employee_id' => $validated['employee_id'],
            'document_name' => $validated['document_name'],
            'document_category' => $validated['document_category'],
            'document_type' => $validated['document_type'],
            'file_path' => $filePath,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $user->id,
        ]);

        $document->load(['employee:id,first_name,last_name,employee_code', 'uploader:id,name']);

        // Audit Log
        Log::info('Employee Document Uploaded', [
            'document_id' => $document->id,
            'employee_id' => $document->employee_id,
            'document_name' => $document->document_name,
            'document_category' => $document->document_category,
            'document_type' => $document->document_type,
            'uploaded_by' => $user->id,
            'ip_address' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        \App\Services\AuditService::logModelChange(
            'document.uploaded',
            $document,
            [],
            $document->getAttributes(),
            "Uploaded document {$document->document_name} ({$document->document_category} - {$document->document_type})"
        );

        return response()->json($document, 201);
    }

    /**
     * Display the specified document metadata.
     */
    public function show(Request $request, EmployeeDocument $document)
    {
        $user = $request->user();

        if (!$user->can('documents.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if (!$this->isAuthorizedForDocument($user, $document)) {
            return response()->json(['message' => 'You are not authorized to view this document.'], 403);
        }

        $document->load(['employee:id,first_name,last_name,employee_code', 'uploader:id,name']);

        return response()->json($document);
    }

    /**
     * Download the specified document file.
     */
    public function download(Request $request, EmployeeDocument $document)
    {
        $user = $request->user();

        if (!$user->can('documents.view')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if (!$this->isAuthorizedForDocument($user, $document)) {
            return response()->json(['message' => 'You are not authorized to download this document.'], 403);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'Document file not found in storage.'], 404);
        }

        // Audit Log for Download Access
        Log::info('Employee Document Downloaded', [
            'document_id' => $document->id,
            'employee_id' => $document->employee_id,
            'downloaded_by' => $user->id,
            'ip_address' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        \App\Services\AuditService::logModelChange(
            'document.downloaded',
            $document,
            [],
            [],
            "Downloaded document {$document->document_name}"
        );

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $downloadFileName = $document->document_name;
        if (!str_ends_with(strtolower($downloadFileName), '.' . strtolower($extension)) && $extension) {
            $downloadFileName .= '.' . $extension;
        }

        return Storage::disk('local')->download($document->file_path, $downloadFileName, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    /**
     * Remove the specified document.
     */
    public function destroy(Request $request, EmployeeDocument $document)
    {
        $user = $request->user();

        if (!$user->can('documents.delete')) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // Non-admin roles can only delete if it's their own document
        if (!$user->hasAnyRole(['Super Admin', 'HR Admin'])) {
            if ($user->employee?->id !== $document->employee_id && $document->uploaded_by !== $user->id) {
                return response()->json(['message' => 'You are not authorized to delete this document.'], 403);
            }
        }

        // Delete file from private storage
        if (Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $docId = $document->id;
        $employeeId = $document->employee_id;
        $docName = $document->document_name;

        \App\Services\AuditService::logModelChange(
            'document.deleted',
            $document,
            $document->getAttributes(),
            [],
            "Deleted document {$docName}"
        );

        $document->delete();

        // Audit Log
        Log::info('Employee Document Deleted', [
            'document_id' => $docId,
            'employee_id' => $employeeId,
            'document_name' => $docName,
            'deleted_by' => $user->id,
            'ip_address' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json(['message' => 'Document deleted successfully.']);
    }

    /**
     * Helper to verify if user is authorized to access a given document record.
     */
    private function isAuthorizedForDocument($user, EmployeeDocument $document): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'HR Admin', 'HR Executive'])) {
            return true;
        }

        if ($user->hasRole('Manager') && $user->employee) {
            $allowedIds = $user->employee->directReports()->pluck('id')->push($user->employee->id)->toArray();
            return in_array($document->employee_id, $allowedIds, true);
        }

        return $user->employee?->id === $document->employee_id;
    }
}
