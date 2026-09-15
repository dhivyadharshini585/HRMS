<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Shift;

class AttendanceCalculationService
{
    /**
     * Calculates working minutes, status, and overtime based on check-in, check-out, and shift rules.
     *
     * @param string|Carbon $checkIn
     * @param string|Carbon $checkOut
     * @param Shift $shift
     * @param string $attendanceDate (Y-m-d)
     * @return array Contains 'working_minutes', 'status', and 'overtime_minutes'
     */
    public static function calculateMetrics($checkIn, $checkOut, Shift $shift, string $attendanceDate): array
    {
        $checkIn = Carbon::parse($checkIn);
        $checkOut = Carbon::parse($checkOut);

        $workingMinutes = (int) $checkIn->diffInMinutes($checkOut);

        // Expected check-in time for Late logic recalculation if needed
        $dateStr = Carbon::parse($attendanceDate)->toDateString();
        $expectedCheckIn = Carbon::parse($dateStr . ' ' . $shift->start_time);
        $lateThreshold = $expectedCheckIn->copy()->addMinutes($shift->grace_period_minutes ?? 0);

        // Status calculation rule:
        // 1. working_minutes < 240 => Half Day
        // 2. otherwise check_in > lateThreshold => Late
        // 3. otherwise => Present
        if ($workingMinutes < 240) {
            $finalStatus = 'Half Day';
        } elseif ($checkIn->greaterThan($lateThreshold)) {
            $finalStatus = 'Late';
        } else {
            $finalStatus = 'Present';
        }

        $isNightShift = $shift->start_time > $shift->end_time;
        // Expected end time
        $expectedEndTime = Carbon::parse($dateStr . ' ' . $shift->end_time);
        if ($isNightShift) {
            $expectedEndTime->addDay();
        }

        // Overtime calculation
        $overtimeMinutes = 0;
        if ($shift->overtime_enabled && $shift->overtime_threshold_minutes !== null) {
            // Difference between actual checkout and expected end time
            if ($checkOut->greaterThan($expectedEndTime)) {
                $extraMinutes = (int) $expectedEndTime->diffInMinutes($checkOut);
                if ($extraMinutes >= $shift->overtime_threshold_minutes) {
                    $overtimeMinutes = $extraMinutes;
                }
            }
        }

        // Early Exit calculation
        $earlyExitMinutes = 0;
        if ($checkOut->lessThan($expectedEndTime)) {
            $earlyExitMinutes = (int) $checkOut->diffInMinutes($expectedEndTime);
        }

        return [
            'working_minutes' => $workingMinutes,
            'status' => $finalStatus,
            'overtime_minutes' => $overtimeMinutes,
            'early_exit_minutes' => $earlyExitMinutes,
        ];
    }
}
