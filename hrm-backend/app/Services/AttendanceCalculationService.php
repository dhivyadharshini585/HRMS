<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Shift;

class AttendanceCalculationService
{
    /**
     * Calculate attendance metrics like working minutes, status, overtime, and early exit.
     *
     * @param string|Carbon $checkIn
     * @param string|Carbon $checkOut
     * @param Shift $shift
     * @param string $attendanceDate
     * @return array
     */
    public static function calculateMetrics($checkIn, $checkOut, Shift $shift, $attendanceDate)
    {
        $checkIn = Carbon::parse($checkIn);
        $checkOut = Carbon::parse($checkOut);
        $workingMinutes = (int) $checkIn->diffInMinutes($checkOut);

        $dateStr = Carbon::parse($attendanceDate)->toDateString();
        $isNightShift = $shift->start_time > $shift->end_time;

        // 1. Calculate Expected Check In & Late Threshold
        $expectedCheckIn = Carbon::parse($dateStr . ' ' . $shift->start_time);
        $lateThreshold = $expectedCheckIn->copy()->addMinutes($shift->grace_period_minutes ?? 0);

        // 2. Calculate Final Status
        if ($workingMinutes < 240) {
            $finalStatus = 'Half Day';
        } elseif ($checkIn->greaterThan($lateThreshold)) {
            $finalStatus = 'Late';
        } else {
            $finalStatus = 'Present';
        }

        // 3. Calculate Overtime & Early Exit
        $overtimeMinutes = 0;
        $earlyExitMinutes = 0;

        $expectedEndTime = Carbon::parse($dateStr . ' ' . $shift->end_time);
        if ($isNightShift) {
            $expectedEndTime->addDay();
        }

        // Early Exit Check
        if ($checkOut->lessThan($expectedEndTime)) {
            $earlyExitMinutes = (int) $checkOut->diffInMinutes($expectedEndTime);
        }

        // Overtime Check
        if ($shift->overtime_enabled && $shift->overtime_threshold_minutes !== null) {
            if ($checkOut->greaterThan($expectedEndTime)) {
                $extraMinutes = (int) $expectedEndTime->diffInMinutes($checkOut);
                if ($extraMinutes >= $shift->overtime_threshold_minutes) {
                    $overtimeMinutes = $extraMinutes;
                }
            }
        }

        return [
            'working_minutes' => $workingMinutes,
            'status' => $finalStatus,
            'overtime_minutes' => $overtimeMinutes,
            'early_exit_minutes' => $earlyExitMinutes
        ];
    }
}
