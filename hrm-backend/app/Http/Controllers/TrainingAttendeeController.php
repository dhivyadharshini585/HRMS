<?php

namespace App\Http\Controllers;

use App\Models\TrainingAttendee;
use App\Models\Training;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrainingAttendeeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        $query = TrainingAttendee::with(['training.trainer', 'employee']);

        if (!$user->can('training.manage')) {
            if ($employeeId) {
                $query->where('employee_id', $employeeId);
            } else {
                $query->where('id', '<', 0);
            }
        }

        if ($request->filled('training_id')) {
            $query->where('training_id', $request->training_id);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        $attendees = $query->get();
        return response()->json($attendees);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'training_id' => 'required|exists:trainings,id',
            'employee_id' => 'required|exists:employees,id',
            'completion_status' => 'nullable|string|in:enrolled,completed,failed,dropped',
        ]);

        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('training.manage')) {
            if ($validated['employee_id'] != $employeeId) {
                return response()->json(['message' => 'Unauthorized to enroll this employee'], 403);
            }
        }

        // Check if already enrolled
        $existing = TrainingAttendee::where('training_id', $validated['training_id'])
                                     ->where('employee_id', $validated['employee_id'])
                                     ->first();
        
        if ($existing) {
            return response()->json(['message' => 'Employee is already enrolled in this training'], 422);
        }

        if (!isset($validated['completion_status'])) {
            $validated['completion_status'] = 'enrolled';
        }

        $attendee = TrainingAttendee::create($validated);

        return response()->json([
            'message' => 'Enrolled successfully',
            'data' => $attendee
        ], 201);
    }

    public function show(Request $request, TrainingAttendee $training_attendee)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('training.manage') && $training_attendee->employee_id != $employeeId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $training_attendee->load(['training', 'employee']);
        return response()->json($training_attendee);
    }

    public function update(Request $request, TrainingAttendee $training_attendee)
    {
        $validated = $request->validate([
            'completion_status' => 'required|string|in:enrolled,completed,failed,dropped',
        ]);

        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('training.manage') && $training_attendee->employee_id != $employeeId) {
            return response()->json(['message' => 'Unauthorized to update this record'], 403);
        }

        DB::beginTransaction();
        try {
            $training_attendee->update($validated);

            // Generate certificate if status is completed
            if ($validated['completion_status'] === 'completed') {
                $training_attendee->load(['training.trainer', 'employee']);
                $training = $training_attendee->training;
                $employee = $training_attendee->employee;
                
                // Check if document already exists
                $docName = $training->training_name . ' Certificate';
                $existingDoc = EmployeeDocument::where('employee_id', $training_attendee->employee_id)
                                             ->where('document_category', 'Certificate')
                                             ->where('document_name', $docName)
                                             ->first();
                
                if (!$existingDoc) {
                    $stubFileName = 'certificates/' . uniqid() . '.pdf';
                    
                    if (!\Illuminate\Support\Facades\Storage::disk('local')->exists('certificates')) {
                        \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory('certificates');
                    }

                    $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
                    if (empty($employeeName)) {
                        $employeeName = 'Employee #' . $training_attendee->employee_id;
                    }

                    $trainerName = 'N/A';
                    if ($training && $training->trainer) {
                        $trainerName = trim(($training->trainer->first_name ?? '') . ' ' . ($training->trainer->last_name ?? ''));
                    }

                    $pdfBinary = \App\Services\CertificateGenerator::generatePdf([
                        'employee_name' => $employeeName,
                        'training_name' => $training->training_name ?? 'Training',
                        'trainer_name' => $trainerName,
                        'start_date' => $training->start_date ?? 'N/A',
                        'end_date' => $training->end_date ?? 'N/A',
                        'completion_status' => 'Completed',
                    ]);

                    \Illuminate\Support\Facades\Storage::disk('local')->put($stubFileName, $pdfBinary);

                    EmployeeDocument::create([
                        'employee_id' => $training_attendee->employee_id,
                        'document_name' => $docName,
                        'document_category' => 'Certificate',
                        'document_type' => 'System Generated',
                        'file_path' => $stubFileName,
                        'mime_type' => 'application/pdf',
                        'file_size' => strlen($pdfBinary),
                        'uploaded_by' => $user->id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Training attendee updated successfully',
                'data' => $training_attendee
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error updating record: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, TrainingAttendee $training_attendee)
    {
        $user = $request->user();
        $employeeId = $user->employee->id ?? null;

        if (!$user->can('training.manage') && $training_attendee->employee_id != $employeeId) {
            return response()->json(['message' => 'Unauthorized to delete this record'], 403);
        }

        $training_attendee->delete();

        return response()->json([
            'message' => 'Unenrolled successfully'
        ]);
    }
}
