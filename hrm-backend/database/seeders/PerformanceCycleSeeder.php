<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PerformanceCycle;

class PerformanceCycleSeeder extends Seeder
{
    public function run(): void
    {
        PerformanceCycle::firstOrCreate(
            ['name' => '2025 Annual Performance Cycle'],
            ['start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'status' => 'completed']
        );
        
        PerformanceCycle::firstOrCreate(
            ['name' => '2026 Annual Performance Cycle'],
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'active']
        );
    }
}
