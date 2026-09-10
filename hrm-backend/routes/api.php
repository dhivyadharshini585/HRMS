<?php

use App\Models\Employee;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'HRMS API is running'
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    });
    
    Route::post('/logout', [AuthController::class, 'logout']);

    // Lookup endpoints are now handled by DepartmentController@index and DesignationController@index
    // We use a combined permission so both department managers and employee managers can list them.
    Route::get('/departments', [App\Http\Controllers\DepartmentController::class, 'index'])->middleware('permission:departments.view|employees.view|employees.create|employees.update');
    Route::post('/departments', [App\Http\Controllers\DepartmentController::class, 'store'])->middleware('permission:departments.create');
    Route::get('/departments/{department}', [App\Http\Controllers\DepartmentController::class, 'show'])->middleware('permission:departments.view|departments.update');
    Route::put('/departments/{department}', [App\Http\Controllers\DepartmentController::class, 'update'])->middleware('permission:departments.update');
    Route::delete('/departments/{department}', [App\Http\Controllers\DepartmentController::class, 'destroy'])->middleware('permission:departments.delete');

    Route::get('/designations', [App\Http\Controllers\DesignationController::class, 'index'])->middleware('permission:designations.view|employees.view|employees.create|employees.update');
    Route::post('/designations', [App\Http\Controllers\DesignationController::class, 'store'])->middleware('permission:designations.create');
    Route::get('/designations/{designation}', [App\Http\Controllers\DesignationController::class, 'show'])->middleware('permission:designations.view|designations.update');
    Route::put('/designations/{designation}', [App\Http\Controllers\DesignationController::class, 'update'])->middleware('permission:designations.update');
    Route::delete('/designations/{designation}', [App\Http\Controllers\DesignationController::class, 'destroy'])->middleware('permission:designations.delete');

    // Employee & Profile endpoints
    Route::get('/me/profile', [EmployeeController::class, 'meProfile']);
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:employees.view');
    Route::post('/employees', [EmployeeController::class, 'store'])->middleware('permission:employees.create');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show']);
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->middleware('permission:employees.update');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.delete');

    // Document Management endpoints
    Route::get('/documents', [App\Http\Controllers\DocumentController::class, 'index']);
    Route::post('/documents', [App\Http\Controllers\DocumentController::class, 'store']);
    Route::get('/documents/{document}', [App\Http\Controllers\DocumentController::class, 'show']);
    Route::get('/documents/{document}/download', [App\Http\Controllers\DocumentController::class, 'download']);
    Route::delete('/documents/{document}', [App\Http\Controllers\DocumentController::class, 'destroy']);

    // Audit Log endpoints
    Route::get('/audit-logs', [App\Http\Controllers\AuditLogController::class, 'index'])->middleware('permission:audit.view');
    Route::get('/audit-logs/{auditLog}', [App\Http\Controllers\AuditLogController::class, 'show'])->middleware('permission:audit.view');

    // Attendance Management endpoints
    Route::get('/attendance', [App\Http\Controllers\AttendanceController::class, 'index'])->middleware('permission:attendance.view');
    Route::get('/attendance/today', [App\Http\Controllers\AttendanceController::class, 'today'])->middleware('permission:attendance.view');
    Route::post('/attendance/check-in', [App\Http\Controllers\AttendanceController::class, 'checkIn'])->middleware('permission:attendance.view');
    Route::post('/attendance/check-out', [App\Http\Controllers\AttendanceController::class, 'checkOut'])->middleware('permission:attendance.view');

    // Shift Management endpoints
    Route::get('/shifts', [App\Http\Controllers\ShiftController::class, 'index'])->middleware('permission:shifts.view');
    Route::get('/shifts/{shift}', [App\Http\Controllers\ShiftController::class, 'show'])->middleware('permission:shifts.view');
    Route::post('/shifts', [App\Http\Controllers\ShiftController::class, 'store'])->middleware('permission:shifts.manage');
    Route::put('/shifts/{shift}', [App\Http\Controllers\ShiftController::class, 'update'])->middleware('permission:shifts.manage');
    Route::delete('/shifts/{shift}', [App\Http\Controllers\ShiftController::class, 'destroy'])->middleware('permission:shifts.manage');
    Route::post('/employees/{employee}/assign-shift', [App\Http\Controllers\ShiftController::class, 'assignToEmployee'])->middleware('permission:shifts.manage');
    Route::get('/employees/{employee}/rotations', [App\Http\Controllers\ShiftController::class, 'listRotations']);
    Route::get('/employees/{employee}/shift-rotations', [App\Http\Controllers\ShiftController::class, 'listRotations']);
    Route::post('/employees/{employee}/rotations', [App\Http\Controllers\ShiftController::class, 'createRotation'])->middleware('permission:shifts.manage');
    Route::post('/employees/{employee}/shift-rotations', [App\Http\Controllers\ShiftController::class, 'createRotation'])->middleware('permission:shifts.manage');
    Route::delete('/rotations/{rotation}', [App\Http\Controllers\ShiftController::class, 'deleteRotation'])->middleware('permission:shifts.manage');
    Route::delete('/shift-rotations/{rotation}', [App\Http\Controllers\ShiftController::class, 'deleteRotation'])->middleware('permission:shifts.manage');

    // Holiday Management endpoints
    Route::get('/holidays', [App\Http\Controllers\HolidayController::class, 'index'])->middleware('permission:holidays.view');
    Route::get('/holidays/{holiday}', [App\Http\Controllers\HolidayController::class, 'show'])->middleware('permission:holidays.view');
    Route::post('/holidays', [App\Http\Controllers\HolidayController::class, 'store'])->middleware('permission:holidays.manage');
    Route::put('/holidays/{holiday}', [App\Http\Controllers\HolidayController::class, 'update'])->middleware('permission:holidays.manage');
    Route::delete('/holidays/{holiday}', [App\Http\Controllers\HolidayController::class, 'destroy'])->middleware('permission:holidays.manage');

    // Leave Management endpoints
    Route::get('/leave-types', [App\Http\Controllers\LeaveTypeController::class, 'index'])->middleware('permission:leave_types.view|leave.view');
    Route::get('/leave-types/{leaveType}', [App\Http\Controllers\LeaveTypeController::class, 'show'])->middleware('permission:leave_types.view|leave.view');
    Route::post('/leave-types', [App\Http\Controllers\LeaveTypeController::class, 'store'])->middleware('permission:leave_types.manage');
    Route::put('/leave-types/{leaveType}', [App\Http\Controllers\LeaveTypeController::class, 'update'])->middleware('permission:leave_types.manage');
    Route::delete('/leave-types/{leaveType}', [App\Http\Controllers\LeaveTypeController::class, 'destroy'])->middleware('permission:leave_types.manage');

    Route::get('/leave-balances', [App\Http\Controllers\LeaveBalanceController::class, 'index'])->middleware('permission:leave.view');
    Route::post('/employees/{employee}/leave-balances/adjust', [App\Http\Controllers\LeaveBalanceController::class, 'adjust'])->middleware('permission:leave_types.manage|leave.approve');

    Route::get('/leave-requests', [App\Http\Controllers\LeaveRequestController::class, 'index'])->middleware('permission:leave.view');
    Route::post('/leave-requests', [App\Http\Controllers\LeaveRequestController::class, 'store'])->middleware('permission:leave.apply');
    Route::get('/leave-requests/{leaveRequest}', [App\Http\Controllers\LeaveRequestController::class, 'show'])->middleware('permission:leave.view');
    Route::post('/leave-requests/{leaveRequest}/manager-approve', [App\Http\Controllers\LeaveRequestController::class, 'managerApprove'])->middleware('permission:leave.approve');
    Route::post('/leave-requests/{leaveRequest}/hr-approve', [App\Http\Controllers\LeaveRequestController::class, 'hrApprove'])->middleware('permission:leave.approve');
    Route::post('/leave-requests/{leaveRequest}/reject', [App\Http\Controllers\LeaveRequestController::class, 'reject'])->middleware('permission:leave.approve');
    Route::post('/leave-requests/{leaveRequest}/cancel', [App\Http\Controllers\LeaveRequestController::class, 'cancel'])->middleware('permission:leave.apply');

    // Attendance Reports endpoints
    Route::get('/reports/attendance', [App\Http\Controllers\AttendanceReportController::class, 'index'])->middleware('permission:reports.attendance|attendance.view');
    Route::get('/reports/attendance/export', [App\Http\Controllers\AttendanceReportController::class, 'export'])->middleware('permission:reports.attendance|attendance.view');

    // Leave Reports endpoints
    Route::get('/reports/leave', [App\Http\Controllers\LeaveReportController::class, 'index'])->middleware('permission:reports.leave|leave.view');
    Route::get('/reports/leave/export', [App\Http\Controllers\LeaveReportController::class, 'export'])->middleware('permission:reports.leave|leave.view');

    // In-App Notifications endpoints
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-all-read', [App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

    // Job Openings endpoints (Phase 3 Task 1)
    Route::get('/job-openings', [App\Http\Controllers\JobOpeningController::class, 'index'])->middleware('permission:recruitment.jobs.view');
    Route::post('/job-openings', [App\Http\Controllers\JobOpeningController::class, 'store'])->middleware('permission:recruitment.jobs.create');
    Route::get('/job-openings/{id}', [App\Http\Controllers\JobOpeningController::class, 'show'])->middleware('permission:recruitment.jobs.view');
    Route::match(['put', 'patch'], '/job-openings/{id}', [App\Http\Controllers\JobOpeningController::class, 'update'])->middleware('permission:recruitment.jobs.update');
    Route::delete('/job-openings/{id}', [App\Http\Controllers\JobOpeningController::class, 'destroy'])->middleware('permission:recruitment.jobs.delete');

    // Candidates endpoints (Phase 3 Task 2)
    Route::get('/candidates', [App\Http\Controllers\CandidateController::class, 'index'])->middleware('permission:recruitment.candidates.view');
    Route::post('/candidates', [App\Http\Controllers\CandidateController::class, 'store'])->middleware('permission:recruitment.candidates.create');
    Route::get('/candidates/{id}', [App\Http\Controllers\CandidateController::class, 'show'])->middleware('permission:recruitment.candidates.view');
    Route::match(['put', 'patch'], '/candidates/{id}', [App\Http\Controllers\CandidateController::class, 'update'])->middleware('permission:recruitment.candidates.update');
    Route::delete('/candidates/{id}', [App\Http\Controllers\CandidateController::class, 'destroy'])->middleware('permission:recruitment.candidates.delete');
    Route::patch('/candidates/{id}/status', [App\Http\Controllers\CandidateController::class, 'updateStatus'])->middleware('permission:recruitment.candidates.update');
    Route::get('/candidates/{id}/status-history', [App\Http\Controllers\CandidateController::class, 'statusHistory'])->middleware('permission:recruitment.candidates.view');

    // Candidate Resume endpoints (Phase 3 Task 3)
    Route::post('/candidates/{id}/resume', [App\Http\Controllers\CandidateController::class, 'uploadResume'])->middleware('permission:recruitment.candidates.update');
    Route::get('/candidates/{id}/resume', [App\Http\Controllers\CandidateController::class, 'downloadResume'])->middleware('permission:recruitment.candidates.view');
    Route::delete('/candidates/{id}/resume', [App\Http\Controllers\CandidateController::class, 'deleteResume'])->middleware('permission:recruitment.candidates.delete');

    // Interviews endpoints (Phase 3 Task 4)
    Route::get('/interviews', [App\Http\Controllers\InterviewController::class, 'index'])->middleware('permission:recruitment.interviews.view');
    Route::post('/interviews', [App\Http\Controllers\InterviewController::class, 'store'])->middleware('permission:recruitment.interviews.create');
    Route::get('/interviews/{id}', [App\Http\Controllers\InterviewController::class, 'show'])->middleware('permission:recruitment.interviews.view');
    Route::match(['put', 'patch'], '/interviews/{id}', [App\Http\Controllers\InterviewController::class, 'update'])->middleware('permission:recruitment.interviews.update');
    Route::delete('/interviews/{id}', [App\Http\Controllers\InterviewController::class, 'destroy'])->middleware('permission:recruitment.interviews.delete');

    // Interview Feedback endpoints (Phase 3 Task 5)
    Route::get('/interviews/{interview}/feedback', [App\Http\Controllers\InterviewFeedbackController::class, 'show'])->middleware('permission:recruitment.interview_feedback.view');
    Route::post('/interviews/{interview}/feedback', [App\Http\Controllers\InterviewFeedbackController::class, 'store'])->middleware('permission:recruitment.interview_feedback.create');
    Route::match(['put', 'patch'], '/interviews/{interview}/feedback', [App\Http\Controllers\InterviewFeedbackController::class, 'update'])->middleware('permission:recruitment.interview_feedback.update');
    Route::delete('/interviews/{interview}/feedback', [App\Http\Controllers\InterviewFeedbackController::class, 'destroy'])->middleware('permission:recruitment.interview_feedback.delete');

    // Offer Letters endpoints (Phase 3 Task 7)
    Route::get('/offer-letters', [App\Http\Controllers\OfferLetterController::class, 'index'])->middleware('permission:recruitment.offer_letters.view');
    Route::post('/offer-letters', [App\Http\Controllers\OfferLetterController::class, 'store'])->middleware('permission:recruitment.offer_letters.create');
    Route::get('/offer-letters/{id}', [App\Http\Controllers\OfferLetterController::class, 'show'])->middleware('permission:recruitment.offer_letters.view');
    Route::match(['put', 'patch'], '/offer-letters/{id}', [App\Http\Controllers\OfferLetterController::class, 'update'])->middleware('permission:recruitment.offer_letters.update');
    Route::delete('/offer-letters/{id}', [App\Http\Controllers\OfferLetterController::class, 'destroy'])->middleware('permission:recruitment.offer_letters.delete');
    Route::patch('/offer-letters/{id}/send', [App\Http\Controllers\OfferLetterController::class, 'send'])->middleware('permission:recruitment.offer_letters.send');
    Route::patch('/offer-letters/{id}/accept', [App\Http\Controllers\OfferLetterController::class, 'accept'])->middleware('permission:recruitment.offer_letters.respond');
    Route::patch('/offer-letters/{id}/reject', [App\Http\Controllers\OfferLetterController::class, 'reject'])->middleware('permission:recruitment.offer_letters.respond');
    Route::patch('/offer-letters/{id}/withdraw', [App\Http\Controllers\OfferLetterController::class, 'withdraw'])->middleware('permission:recruitment.offer_letters.update|recruitment.offer_letters.send');
    Route::patch('/offer-letters/{id}/expire', [App\Http\Controllers\OfferLetterController::class, 'expire'])->middleware('permission:recruitment.offer_letters.update');
    Route::get('/offer-letters/{id}/download', [App\Http\Controllers\OfferLetterController::class, 'download'])->middleware('permission:recruitment.offer_letters.download|recruitment.offer_letters.view');

    // Employee Onboarding endpoints (Phase 3 Task 8)
    Route::get('/onboarding', [App\Http\Controllers\OnboardingController::class, 'index'])->middleware('permission:recruitment.onboarding.view');
    Route::post('/onboarding', [App\Http\Controllers\OnboardingController::class, 'store'])->middleware('permission:recruitment.onboarding.create');
    Route::get('/onboarding/{id}', [App\Http\Controllers\OnboardingController::class, 'show'])->middleware('permission:recruitment.onboarding.view');
    Route::put('/onboarding/{id}/cancel', [App\Http\Controllers\OnboardingController::class, 'cancel'])->middleware('permission:recruitment.onboarding.delete');
    Route::post('/onboarding/{id}/checklist/{itemId}/submit', [App\Http\Controllers\OnboardingController::class, 'submitChecklist'])->middleware('permission:recruitment.onboarding.update');
    Route::put('/onboarding/{id}/checklist/{itemId}/verify', [App\Http\Controllers\OnboardingController::class, 'verifyChecklist'])->middleware('permission:recruitment.onboarding.verify');
    Route::put('/onboarding/{id}/checklist/{itemId}/reject', [App\Http\Controllers\OnboardingController::class, 'rejectChecklist'])->middleware('permission:recruitment.onboarding.verify');
    Route::put('/onboarding/{id}/it-account', [App\Http\Controllers\OnboardingController::class, 'updateItAccount'])->middleware('permission:recruitment.onboarding.update');
    Route::put('/onboarding/{id}/laptop-allocation', [App\Http\Controllers\OnboardingController::class, 'updateLaptopAllocation'])->middleware('permission:recruitment.onboarding.update');
    Route::post('/onboarding/{id}/create-employee', [App\Http\Controllers\OnboardingController::class, 'createEmployee'])->middleware('permission:recruitment.onboarding.complete|employees.create');
    Route::put('/onboarding/{id}/complete', [App\Http\Controllers\OnboardingController::class, 'complete'])->middleware('permission:recruitment.onboarding.complete');
    Route::get('/onboarding/{id}/checklist/{itemId}/download', [App\Http\Controllers\OnboardingController::class, 'downloadDocument'])->middleware('permission:recruitment.onboarding.view');

    // Recruitment Reports endpoints (Phase 3 Final Task)
    Route::middleware('permission:recruitment.reports.view')->group(function () {
        Route::get('/reports/recruitment/applications',  [App\Http\Controllers\RecruitmentReportController::class, 'applications']);
        Route::get('/reports/recruitment/shortlisted',   [App\Http\Controllers\RecruitmentReportController::class, 'shortlisted']);
        Route::get('/reports/recruitment/interviews',    [App\Http\Controllers\RecruitmentReportController::class, 'interviews']);
        Route::get('/reports/recruitment/selected',      [App\Http\Controllers\RecruitmentReportController::class, 'selected']);
        Route::get('/reports/recruitment/time-to-hire',  [App\Http\Controllers\RecruitmentReportController::class, 'timeToHire']);
        Route::get('/reports/recruitment/hiring-cost',   [App\Http\Controllers\RecruitmentReportController::class, 'hiringCost']);
    });

    // Salary Structures
    Route::get('/salary-structures', [App\Http\Controllers\SalaryStructureController::class, 'index'])->middleware('permission:payroll.view');
    Route::post('/salary-structures', [App\Http\Controllers\SalaryStructureController::class, 'store'])->middleware('permission:payroll.manage');
    Route::get('/salary-structures/{salaryStructure}', [App\Http\Controllers\SalaryStructureController::class, 'show'])->middleware('permission:payroll.view');
    Route::put('/salary-structures/{salaryStructure}', [App\Http\Controllers\SalaryStructureController::class, 'update'])->middleware('permission:payroll.manage');
    Route::delete('/salary-structures/{salaryStructure}', [App\Http\Controllers\SalaryStructureController::class, 'destroy'])->middleware('permission:payroll.manage');

    // Payroll Processing & Records
    Route::get('/payrolls', [App\Http\Controllers\PayrollController::class, 'index'])->middleware('permission:payroll.view');
    Route::post('/payrolls', [App\Http\Controllers\PayrollController::class, 'store'])->middleware('permission:payroll.manage');
    Route::get('/payrolls/{payroll}', [App\Http\Controllers\PayrollController::class, 'show'])->middleware('permission:payroll.view');
    Route::post('/payrolls/{payroll}/approve', [App\Http\Controllers\PayrollController::class, 'approve'])->middleware('permission:payroll.approve');

    // Payslips
    Route::post('/payrolls/{payroll}/payslips', [App\Http\Controllers\PayslipController::class, 'generate'])->middleware('permission:payroll.manage');
    Route::get('/payslips/{payslip}', [App\Http\Controllers\PayslipController::class, 'show']);

    // Payroll Reports
    Route::get('/reports/payroll/summary', [App\Http\Controllers\PayrollReportController::class, 'monthlySummary'])->middleware('permission:payroll.view');
    Route::get('/reports/payroll/department', [App\Http\Controllers\PayrollReportController::class, 'departmentWise'])->middleware('permission:payroll.view');
    Route::get('/reports/payroll/deductions', [App\Http\Controllers\PayrollReportController::class, 'deductionReport'])->middleware('permission:payroll.view');
    Route::get('/reports/payroll/employee', [App\Http\Controllers\PayrollReportController::class, 'employeeReport'])->middleware('permission:payroll.view');

    // RBAC Test Endpoint
    Route::get('/rbac-test', function (Request $request) {
        return response()->json([
            'message' => 'RBAC access granted',
            'role' => $request->user()->getRoleNames()->first()
        ]);
    })->middleware('permission:dashboard.view');
});
