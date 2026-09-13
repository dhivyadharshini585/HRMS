<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReview;
use Illuminate\Http\Request;

class PerformanceReportController extends Controller
{
    /**
     * Get Employee Ratings Report.
     */
    public function employeeRatings(Request $request)
    {
        $user = $request->user();

        // Finance/Payroll Admin explicitly forbidden from performance reports if not HR/Super Admin
        if ($user->hasRole('Finance/Payroll Admin') && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return response()->json(['message' => 'Unauthorized action. Finance/Payroll Admin does not have access to performance reports.'], 403);
        }

        $query = PerformanceReview::with(['employee.department', 'employee.designation', 'reviewer', 'cycle']);

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $employee = $user->employee;

        if ($isHR) {
            // Company-wide access
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->filled('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
        } elseif ($user->hasRole('Manager')) {
            if (!$employee) {
                return response()->json(['message' => 'No linked employee profile found for manager.'], 400);
            }

            $allowedEmployeeIds = Employee::where('manager_id', $employee->id)
                ->pluck('id')
                ->push($employee->id)
                ->toArray();

            if ($request->filled('employee_id')) {
                if (!in_array((int)$request->employee_id, $allowedEmployeeIds)) {
                    return response()->json(['message' => 'Unauthorized. You can only view performance reports for yourself and your direct reports.'], 403);
                }
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->whereIn('employee_id', $allowedEmployeeIds);
            }

            if ($request->filled('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
        } elseif ($user->hasRole('Employee')) {
            if (!$employee) {
                return response()->json(['message' => 'No linked employee profile found for user.'], 400);
            }

            if ($request->filled('employee_id') && (int)$request->employee_id !== $employee->id) {
                return response()->json(['message' => 'Unauthorized. You can only view your own performance report.'], 403);
            }
            $query->where('employee_id', $employee->id);
        } else {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // Filter by Performance Cycle
        if ($request->filled('cycle_id')) {
            $query->where('cycle_id', $request->cycle_id);
        }

        // Filter by Status if provided
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reviews = $query->orderBy('created_at', 'desc')->get();

        $formattedData = $reviews->map(function ($r) {
            $sum = (int)$r->rating_technical_skills
                 + (int)$r->rating_communication
                 + (int)$r->rating_teamwork
                 + (int)$r->rating_leadership
                 + (int)$r->rating_productivity
                 + (int)$r->rating_problem_solving
                 + (int)$r->rating_attendance
                 + (int)$r->rating_goal_achievement;

            $overall = round($sum / 8, 2);

            $empName = $r->employee ? trim("{$r->employee->first_name} {$r->employee->last_name}") : 'N/A';
            $reviewerName = $r->reviewer ? trim("{$r->reviewer->first_name} {$r->reviewer->last_name}") : 'N/A';

            return [
                'id' => $r->id,
                'employee_id' => $r->employee_id,
                'employee_name' => $empName,
                'employee_code' => $r->employee->employee_code ?? 'N/A',
                'department_id' => $r->employee->department_id ?? null,
                'department_name' => $r->employee->department->name ?? 'N/A',
                'designation_name' => $r->employee->designation->name ?? 'N/A',
                'cycle_id' => $r->cycle_id,
                'cycle_name' => $r->cycle->name ?? 'N/A',
                'reviewer_id' => $r->reviewer_id,
                'reviewer_name' => $reviewerName,
                'rating_technical_skills' => (int)$r->rating_technical_skills,
                'rating_communication' => (int)$r->rating_communication,
                'rating_teamwork' => (int)$r->rating_teamwork,
                'rating_leadership' => (int)$r->rating_leadership,
                'rating_productivity' => (int)$r->rating_productivity,
                'rating_problem_solving' => (int)$r->rating_problem_solving,
                'rating_attendance' => (int)$r->rating_attendance,
                'rating_goal_achievement' => (int)$r->rating_goal_achievement,
                'overall_rating' => $overall,
                'comments' => $r->comments,
                'status' => ucfirst($r->status),
                'created_at' => $r->created_at ? $r->created_at->toDateString() : null,
            ];
        });

        $totalReviews = $formattedData->count();
        $avgOverall = $totalReviews > 0 ? round($formattedData->avg('overall_rating'), 2) : 0;

        return response()->json([
            'data' => $formattedData,
            'summary' => [
                'total_reviews' => $totalReviews,
                'average_overall_rating' => $avgOverall,
            ]
        ]);
    }

    /**
     * Get Department Performance Report.
     */
    public function departmentPerformance(Request $request)
    {
        $user = $request->user();

        // Finance/Payroll Admin explicitly forbidden from performance reports if not HR/Super Admin
        if ($user->hasRole('Finance/Payroll Admin') && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return response()->json(['message' => 'Unauthorized action. Finance/Payroll Admin does not have access to performance reports.'], 403);
        }

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $userEmployee = $user->employee;

        $deptQuery = \App\Models\Department::query();

        if (!$isHR) {
            if ($user->hasRole('Manager') && $userEmployee) {
                $allowedDeptIds = Employee::where('manager_id', $userEmployee->id)
                    ->pluck('department_id')
                    ->push($userEmployee->department_id)
                    ->filter()
                    ->unique()
                    ->toArray();

                $deptQuery->whereIn('id', $allowedDeptIds);
            } elseif ($user->hasRole('Employee') && $userEmployee) {
                $deptQuery->where('id', $userEmployee->department_id);
            } else {
                return response()->json(['message' => 'Unauthorized action.'], 403);
            }
        }

        if ($request->filled('department_id')) {
            $deptQuery->where('id', $request->department_id);
        }

        $departments = $deptQuery->orderBy('name', 'asc')->get();

        $cycleId = $request->input('cycle_id');

        $reportData = $departments->map(function ($dept) use ($cycleId) {
            $employees = Employee::where('department_id', $dept->id)->get();
            $employeeCount = $employees->count();
            $employeeIds = $employees->pluck('id')->toArray();

            // Performance Reviews for employees in department
            $reviewsQuery = PerformanceReview::whereIn('employee_id', $employeeIds);
            if ($cycleId) {
                $reviewsQuery->where('cycle_id', $cycleId);
            }
            $reviews = $reviewsQuery->get();

            // Calculate Ratings
            $completedReviews = 0;
            $pendingReviews = 0;
            $overallRatingsSum = 0;
            $validReviewsCount = 0;

            foreach ($reviews as $r) {
                if (in_array(strtolower($r->status), ['submitted', 'acknowledged', 'completed'])) {
                    $completedReviews++;
                } else {
                    $pendingReviews++;
                }

                $sum = (int)$r->rating_technical_skills
                     + (int)$r->rating_communication
                     + (int)$r->rating_teamwork
                     + (int)$r->rating_leadership
                     + (int)$r->rating_productivity
                     + (int)$r->rating_problem_solving
                     + (int)$r->rating_attendance
                     + (int)$r->rating_goal_achievement;

                $overall = $sum / 8;
                $overallRatingsSum += $overall;
                $validReviewsCount++;
            }

            // Employees with no review record in cycle are also counted as pending reviews
            $employeesWithReviewCount = $reviews->pluck('employee_id')->unique()->count();
            $unreviewedEmployeesCount = max(0, $employeeCount - $employeesWithReviewCount);
            $pendingReviews += $unreviewedEmployeesCount;

            $averageOverallRating = $validReviewsCount > 0 ? round($overallRatingsSum / $validReviewsCount, 2) : 0.00;

            // Performance Goals for employees in department
            $goalsQuery = \App\Models\PerformanceGoal::whereIn('employee_id', $employeeIds);
            if ($cycleId) {
                $goalsQuery->where('cycle_id', $cycleId);
            }
            $goals = $goalsQuery->get();
            $totalGoals = $goals->count();
            $completedGoals = $goals->filter(fn($g) => strtolower($g->status) === 'completed' || (int)$g->progress === 100)->count();

            $goalCompletionPercentage = $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100, 2) : 0.00;

            return [
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'employee_count' => $employeeCount,
                'average_overall_rating' => $averageOverallRating,
                'goal_completion_percentage' => $goalCompletionPercentage,
                'completed_reviews' => $completedReviews,
                'pending_reviews' => $pendingReviews,
            ];
        });

        $totalDepartments = $reportData->count();
        $ratedDepts = $reportData->where('average_overall_rating', '>', 0);
        $overallAvgRating = $ratedDepts->count() > 0
            ? round($ratedDepts->avg('average_overall_rating'), 2)
            : 0.00;

        $avgGoalCompletion = $totalDepartments > 0
            ? round($reportData->avg('goal_completion_percentage'), 2)
            : 0.00;

        return response()->json([
            'data' => $reportData->values(),
            'summary' => [
                'total_departments' => $totalDepartments,
                'overall_company_avg_rating' => $overallAvgRating,
                'overall_goal_completion_percentage' => $avgGoalCompletion,
            ]
        ]);
    }

    /**
     * Get Goal Completion Report.
     */
    public function goalCompletion(Request $request)
    {
        $user = $request->user();

        // Finance/Payroll Admin explicitly forbidden from performance reports if not HR/Super Admin
        if ($user->hasRole('Finance/Payroll Admin') && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return response()->json(['message' => 'Unauthorized action. Finance/Payroll Admin does not have access to performance reports.'], 403);
        }

        $query = \App\Models\PerformanceGoal::with(['employee.department', 'cycle']);

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $userEmployee = $user->employee;

        if ($isHR) {
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }
            if ($request->filled('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
        } elseif ($user->hasRole('Manager')) {
            if (!$userEmployee) {
                return response()->json(['message' => 'No linked employee profile found for manager.'], 400);
            }

            $allowedEmployeeIds = Employee::where('manager_id', $userEmployee->id)
                ->pluck('id')
                ->push($userEmployee->id)
                ->toArray();

            if ($request->filled('employee_id')) {
                if (!in_array((int)$request->employee_id, $allowedEmployeeIds)) {
                    return response()->json(['message' => 'Unauthorized. You can only view goals for yourself and your direct reports.'], 403);
                }
                $query->where('employee_id', $request->employee_id);
            } else {
                $query->whereIn('employee_id', $allowedEmployeeIds);
            }

            if ($request->filled('department_id')) {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }
        } elseif ($user->hasRole('Employee')) {
            if (!$userEmployee) {
                return response()->json(['message' => 'No linked employee profile found for user.'], 400);
            }

            if ($request->filled('employee_id') && (int)$request->employee_id !== $userEmployee->id) {
                return response()->json(['message' => 'Unauthorized. You can only view your own goal report.'], 403);
            }
            $query->where('employee_id', $userEmployee->id);
        } else {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        if ($request->filled('cycle_id')) {
            $query->where('cycle_id', $request->cycle_id);
        }

        $goals = $query->orderBy('deadline', 'asc')->get();

        $today = now()->toDateString();
        $completedCount = 0;
        $inProgressCount = 0;
        $pendingCount = 0;
        $cancelledCount = 0;
        $overdueCount = 0;
        $totalProgress = 0;

        $formattedGoals = $goals->map(function ($g) use ($today, &$completedCount, &$inProgressCount, &$pendingCount, &$cancelledCount, &$overdueCount, &$totalProgress) {
            $statusRaw = strtolower($g->status);
            $progressVal = (int)$g->progress;
            $totalProgress += $progressVal;

            $isCompleted = ($statusRaw === 'completed' || $progressVal === 100);

            $deadlineStr = $g->deadline ? ($g->deadline instanceof \Carbon\Carbon ? $g->deadline->toDateString() : substr((string)$g->deadline, 0, 10)) : null;

            $isOverdue = false;
            if ($deadlineStr && $deadlineStr < $today && !$isCompleted && $statusRaw !== 'cancelled') {
                $isOverdue = true;
                $overdueCount++;
            }

            if ($isCompleted) {
                $completedCount++;
            } elseif ($statusRaw === 'in_progress') {
                $inProgressCount++;
            } elseif ($statusRaw === 'cancelled') {
                $cancelledCount++;
            } else {
                $pendingCount++;
            }

            $empName = $g->employee ? trim("{$g->employee->first_name} {$g->employee->last_name}") : 'N/A';

            return [
                'goal_id' => $g->id,
                'employee_id' => $g->employee_id,
                'employee_name' => $empName,
                'employee_code' => $g->employee->employee_code ?? 'N/A',
                'department_id' => $g->employee->department_id ?? null,
                'department_name' => $g->employee->department->name ?? 'N/A',
                'cycle_id' => $g->cycle_id,
                'cycle_name' => $g->cycle->name ?? 'N/A',
                'goal' => $g->goal,
                'target' => $g->target,
                'deadline' => $deadlineStr,
                'progress' => $progressVal,
                'status' => ucfirst($g->status),
                'overdue' => $isOverdue,
            ];
        });

        $totalGoals = $formattedGoals->count();
        $averageProgress = $totalGoals > 0 ? round($totalProgress / $totalGoals, 2) : 0.00;
        $goalCompletionPercentage = $totalGoals > 0 ? round(($completedCount / $totalGoals) * 100, 2) : 0.00;

        return response()->json([
            'data' => $formattedGoals,
            'summary' => [
                'total_goals' => $totalGoals,
                'completed_goals' => $completedCount,
                'in_progress_goals' => $inProgressCount,
                'pending_goals' => $pendingCount,
                'cancelled_goals' => $cancelledCount,
                'average_progress' => $averageProgress,
                'goal_completion_percentage' => $goalCompletionPercentage,
                'overdue_goals' => $overdueCount,
            ]
        ]);
    }

    /**
     * Get Training Completion Report.
     */
    public function trainingCompletion(Request $request)
    {
        $user = $request->user();

        // Finance/Payroll Admin explicitly forbidden from performance reports if not HR/Super Admin
        if ($user->hasRole('Finance/Payroll Admin') && !$user->hasRole(['Super Admin', 'HR Admin'])) {
            return response()->json(['message' => 'Unauthorized action. Finance/Payroll Admin does not have access to performance reports.'], 403);
        }

        $isHR = $user->hasRole(['Super Admin', 'HR Admin', 'HR Executive']);
        $userEmployee = $user->employee;

        $trainingsQuery = \App\Models\Training::with(['trainer', 'attendees.employee.department']);

        if ($request->filled('training_id')) {
            $trainingsQuery->where('id', $request->training_id);
        }

        $trainings = $trainingsQuery->orderBy('start_date', 'desc')->get();

        $totalTrainings = 0;
        $totalEnrollments = 0;
        $completedEnrollments = 0;
        $enrolledPendingEnrollments = 0;
        $failedEnrollments = 0;
        $droppedEnrollments = 0;
        $certificatesIssuedTotal = 0;

        $reportData = $trainings->map(function ($t) use ($request, $isHR, $userEmployee, &$totalTrainings, &$totalEnrollments, &$completedEnrollments, &$enrolledPendingEnrollments, &$failedEnrollments, &$droppedEnrollments, &$certificatesIssuedTotal) {
            $docName = $t->training_name . ' Certificate';

            $attendeesQuery = $t->attendees();

            // Filter attendees by RBAC scoping
            if (!$isHR) {
                if ($user->hasRole('Manager') && $userEmployee) {
                    $allowedEmployeeIds = Employee::where('manager_id', $userEmployee->id)
                        ->pluck('id')
                        ->push($userEmployee->id)
                        ->toArray();

                    $attendeesQuery->whereIn('employee_id', $allowedEmployeeIds);
                } elseif ($user->hasRole('Employee') && $userEmployee) {
                    $attendeesQuery->where('employee_id', $userEmployee->id);
                } else {
                    $attendeesQuery->where('id', '<', 0);
                }
            }

            if ($request->filled('employee_id')) {
                $attendeesQuery->where('employee_id', $request->employee_id);
            }

            if ($request->filled('department_id')) {
                $attendeesQuery->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department_id);
                });
            }

            $attendees = $attendeesQuery->with(['employee.department'])->get();

            $tEnrolled = $attendees->count();

            // Skip training from report if filters specified and 0 matching attendees exist
            if (($request->filled('department_id') || $request->filled('employee_id')) && $tEnrolled === 0) {
                return null;
            }

            $tCompleted = 0;
            $tPending = 0;
            $tFailed = 0;
            $tDropped = 0;
            $tCerts = 0;

            $formattedAttendees = $attendees->map(function ($att) use ($docName, &$tCompleted, &$tPending, &$tFailed, &$tDropped, &$tCerts) {
                $st = strtolower($att->completion_status);
                if ($st === 'completed') {
                    $tCompleted++;
                } elseif ($st === 'failed') {
                    $tFailed++;
                } elseif ($st === 'dropped') {
                    $tDropped++;
                } else {
                    $tPending++;
                }

                // Specific certificate check for this training & employee
                $hasCert = \App\Models\EmployeeDocument::where('employee_id', $att->employee_id)
                    ->where('document_category', 'Certificate')
                    ->where('document_name', $docName)
                    ->exists();

                if ($hasCert) {
                    $tCerts++;
                }

                $empName = $att->employee ? trim("{$att->employee->first_name} {$att->employee->last_name}") : 'N/A';

                return [
                    'attendee_id' => $att->id,
                    'employee_id' => $att->employee_id,
                    'employee_name' => $empName,
                    'employee_code' => $att->employee->employee_code ?? 'N/A',
                    'department_name' => $att->employee->department->name ?? 'N/A',
                    'completion_status' => ucfirst($att->completion_status),
                    'certificate_available' => $hasCert,
                ];
            });

            $tRate = $tEnrolled > 0 ? round(($tCompleted / $tEnrolled) * 100, 2) : 0.00;

            $totalTrainings++;
            $totalEnrollments += $tEnrolled;
            $completedEnrollments += $tCompleted;
            $enrolledPendingEnrollments += $tPending;
            $failedEnrollments += $tFailed;
            $droppedEnrollments += $tDropped;
            $certificatesIssuedTotal += $tCerts;

            $trainerName = $t->trainer ? trim("{$t->trainer->first_name} {$t->trainer->last_name}") : 'N/A';
            $startDateStr = $t->start_date ? ($t->start_date instanceof \Carbon\Carbon ? $t->start_date->toDateString() : substr((string)$t->start_date, 0, 10)) : 'N/A';
            $endDateStr = $t->end_date ? ($t->end_date instanceof \Carbon\Carbon ? $t->end_date->toDateString() : substr((string)$t->end_date, 0, 10)) : 'N/A';

            return [
                'training_id' => $t->id,
                'training_name' => $t->training_name,
                'trainer_name' => $trainerName,
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
                'training_status' => ucfirst($t->status),
                'total_enrolled' => $tEnrolled,
                'completed_count' => $tCompleted,
                'enrolled_pending_count' => $tPending,
                'failed_count' => $tFailed,
                'dropped_count' => $tDropped,
                'completion_percentage' => $tRate,
                'certificates_issued' => $tCerts,
                'attendees' => $formattedAttendees,
            ];
        })->filter()->values();

        $overallCompletionPercentage = $totalEnrollments > 0
            ? round(($completedEnrollments / $totalEnrollments) * 100, 2)
            : 0.00;

        return response()->json([
            'data' => $reportData,
            'summary' => [
                'total_trainings' => $totalTrainings,
                'total_enrollments' => $totalEnrollments,
                'completed_enrollments' => $completedEnrollments,
                'enrolled_pending_enrollments' => $enrolledPendingEnrollments,
                'failed_enrollments' => $failedEnrollments,
                'dropped_enrollments' => $droppedEnrollments,
                'overall_completion_percentage' => $overallCompletionPercentage,
                'certificates_issued' => $certificatesIssuedTotal,
            ]
        ]);
    }
}
