<?php

namespace App\Observers;

use App\Models\Employee;
use App\Services\AuditService;

class EmployeeObserver
{
    /**
     * Handle the Employee "created" event.
     */
    public function created(Employee $employee): void
    {
        AuditService::logModelChange(
            'employee.created',
            $employee,
            [],
            $employee->getAttributes(),
            "Employee {$employee->employee_code} ({$employee->full_name}) record created"
        );
    }

    /**
     * Handle the Employee "updated" event.
     */
    public function updated(Employee $employee): void
    {
        $changes = $employee->getChanges();
        
        // Remove timestamps from diff if only updated_at changed
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $original = $employee->getOriginal();
        $oldValues = array_intersect_key($original, $changes);
        $newValues = $changes;

        $action = 'employee.updated';
        $description = "Employee {$employee->employee_code} ({$employee->full_name}) record updated";

        if (array_key_exists('employment_status', $changes)) {
            $action = 'employee.status_changed';
            $oldStatus = $oldValues['employment_status'] ?? 'Unknown';
            $newStatus = $newValues['employment_status'] ?? 'Unknown';
            $description = "Employee {$employee->employee_code} status changed from {$oldStatus} to {$newStatus}";
        }

        AuditService::logModelChange(
            $action,
            $employee,
            $oldValues,
            $newValues,
            $description
        );
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        AuditService::logModelChange(
            'employee.deleted',
            $employee,
            $employee->getOriginal(),
            [],
            "Employee {$employee->employee_code} ({$employee->full_name}) record deleted"
        );
    }
}
