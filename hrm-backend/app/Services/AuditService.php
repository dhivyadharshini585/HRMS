<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Keys that must never be recorded in audit log payloads.
     */
    protected static array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'access_token',
        'secret',
        'file',
    ];

    /**
     * Log a generic system or domain action.
     */
    public static function log(string $action, ?string $description = null, array $context = []): AuditLog
    {
        $user = request()->user();
        $userId = $context['user_id'] ?? $user?->id;
        $employeeId = $context['employee_id'] ?? $user?->employee?->id;

        return AuditLog::create([
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'action' => $action,
            'entity_type' => $context['entity_type'] ?? null,
            'entity_id' => $context['entity_id'] ?? null,
            'description' => $description,
            'old_values' => isset($context['old_values']) ? self::sanitize($context['old_values']) : null,
            'new_values' => isset($context['new_values']) ? self::sanitize($context['new_values']) : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }

    /**
     * Log an Eloquent model creation, update, or deletion change.
     */
    public static function logModelChange(
        string $action,
        Model $model,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null
    ): AuditLog {
        $user = request()->user();
        $employeeId = null;

        if ($model instanceof \App\Models\Employee) {
            $employeeId = $model->id;
        } elseif (isset($model->employee_id)) {
            $employeeId = $model->employee_id;
        } else {
            $employeeId = $user?->employee?->id;
        }

        return self::log($action, $description, [
            'user_id' => $user?->id,
            'employee_id' => $employeeId,
            'entity_type' => get_class($model),
            'entity_id' => $model->getKey(),
            'old_values' => self::sanitize($oldValues),
            'new_values' => self::sanitize($newValues),
        ]);
    }

    /**
     * Recursively sanitize sensitive keys from logged data arrays.
     */
    public static function sanitize($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $clean = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::$sensitiveKeys, true)) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = self::sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * Alias for sanitize method to redact sensitive data.
     */
    public static function redactSensitiveData($data)
    {
        return self::sanitize($data);
    }
}
