<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $time_differences = [10, 20, 30, 40, 50, 60];

        foreach ($time_differences as $index => $differences) {
            Setting::updateOrCreate(
                ['time_difference' => $differences],
                ['is_active' => ($index === 0)]
            );
        }
    }
}