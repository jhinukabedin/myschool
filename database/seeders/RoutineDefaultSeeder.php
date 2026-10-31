<?php

namespace Database\Seeders;

use App\Models\RoutineTimeSlot;
use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;

/**
 * Safe, repeatable defaults for the class routine module. Existing periods and
 * an existing working-day configuration are never overwritten.
 */
class RoutineDefaultSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            ['name' => 'Assembly', 'start_time' => '08:00', 'end_time' => '08:15', 'type' => 'assembly', 'sort_order' => 1],
            ['name' => 'Period 1', 'start_time' => '08:15', 'end_time' => '09:00', 'type' => 'class', 'sort_order' => 2],
            ['name' => 'Period 2', 'start_time' => '09:00', 'end_time' => '09:45', 'type' => 'class', 'sort_order' => 3],
            ['name' => 'Break', 'start_time' => '09:45', 'end_time' => '10:00', 'type' => 'break', 'sort_order' => 4],
            ['name' => 'Period 3', 'start_time' => '10:00', 'end_time' => '10:45', 'type' => 'class', 'sort_order' => 5],
            ['name' => 'Period 4', 'start_time' => '10:45', 'end_time' => '11:30', 'type' => 'class', 'sort_order' => 6],
            ['name' => 'Lunch', 'start_time' => '11:30', 'end_time' => '12:15', 'type' => 'lunch', 'sort_order' => 7],
            ['name' => 'Period 5', 'start_time' => '12:15', 'end_time' => '13:00', 'type' => 'class', 'sort_order' => 8],
            ['name' => 'Period 6', 'start_time' => '13:00', 'end_time' => '13:45', 'type' => 'class', 'sort_order' => 9],
        ];

        foreach ($slots as $slot) {
            RoutineTimeSlot::firstOrCreate(
                ['name' => $slot['name']],
                $slot + ['is_active' => true]
            );
        }

        if (! SchoolSetting::query()->where('key', 'routine_working_days')->exists()) {
            SchoolSetting::put(
                'routine_working_days',
                json_encode([
                    'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
                ], JSON_UNESCAPED_UNICODE),
                'academic'
            );
        }
    }
}
