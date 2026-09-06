<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shift;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shifts = [
            [
                'name' => 'General Shift',
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
                'grace_period_minutes' => 15,
                'overtime_enabled' => false,
                'overtime_threshold_minutes' => 60,
                'is_active' => true,
                'description' => '09:00 AM - 06:00 PM',
            ],
            [
                'name' => 'Morning Shift',
                'start_time' => '06:00:00',
                'end_time' => '15:00:00',
                'grace_period_minutes' => 15,
                'overtime_enabled' => false,
                'overtime_threshold_minutes' => 60,
                'is_active' => true,
                'description' => '06:00 AM - 03:00 PM',
            ],
            [
                'name' => 'Evening Shift',
                'start_time' => '14:00:00',
                'end_time' => '23:00:00',
                'grace_period_minutes' => 15,
                'overtime_enabled' => false,
                'overtime_threshold_minutes' => 60,
                'is_active' => true,
                'description' => '02:00 PM - 11:00 PM',
            ],
            [
                'name' => 'Night Shift',
                'start_time' => '22:00:00',
                'end_time' => '07:00:00',
                'grace_period_minutes' => 15,
                'overtime_enabled' => false,
                'overtime_threshold_minutes' => 60,
                'is_active' => true,
                'description' => '10:00 PM - 07:00 AM',
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::updateOrCreate(
                ['name' => $shift['name']],
                $shift
            );
        }
    }
}
