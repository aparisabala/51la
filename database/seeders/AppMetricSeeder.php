<?php

namespace Database\Seeders;

use App\Models\App;
use App\Models\AppMetric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AppMetricSeeder extends Seeder
{
    /**
     * Seed apps + sample metrics matching the spreadsheet structure.
     * Cumulative values grow each hour; interval = current - previous.
     */
    public function run(): void
    {
        // ── 1. Create Apps ───────────────────────────────────────
        $appNames = [
            'LLS 新号加固',
            'TTMF',
            '15',
            'NEW Tx',
        ];

        $apps = [];
        foreach ($appNames as $name) {
            $apps[] = App::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }

        // ── 2. Seed sample metrics for today ────────────────────
        $date = Carbon::today()->toDateString();

        // Sample cumulative data from the spreadsheet (LLS 新号加固 column)
        // Format: time_slot => [ip_51la, total_install, total_click, click_ratio, ip_click_ratio, conversion_rate]
        $sampleData = [
            '00:00' => [0,     0,     0,     0  ],
            '01:00' => [8075,  4419,  10262, 2.32],
            '02:00' => [14334, 6905,  15439, 2.24],
            '03:00' => [18527, 9276,  20497, 2.21],
            '04:00' => [21180, 10742, 23591, 2.20],
            '05:00' => [23020, 11738, 25757, 2.19],
            '06:00' => [24290, 12403, 27170, 2.19],
            '07:00' => [25424, 13026, 28541, 2.19],
            '08:00' => [26723, 13705, 29983, 2.19],
            '09:00' => [28568, 14607, 31942, 2.19],
            '10:00' => [30643, 15742, 34394, 2.18],
            '11:00' => [33501, 17330, 37847, 2.18],
            '12:00' => [35817, 18609, 40674, 2.19],
            '13:00' => [38505, 19315, 42176, 2.18],
            '14:00' => [42427, 20177, 43876, 2.17],
            '15:00' => [46568, 22431, 49256, 2.20],
            '16:00' => [51061, 24891, 54931, 2.21],
            '17:00' => [54754, 26985, 59584, 2.21],
            '18:00' => [58435, 28989, 64203, 2.21],
            '19:00' => [61509, 30603, 67930, 2.22],
            '20:00' => [64600, 32341, 71840, 2.22],
            '21:00' => [68697, 34592, 77146, 2.23],
            '22:00' => [73456, 37242, 83195, 2.23],
            '23:00' => [79497, 40472, 90472, 2.24],
        ];

        $slots    = array_keys($sampleData);
        $prevData = null;

        foreach ($apps as $appIndex => $app) {
            $prevCumulative = null;

            foreach ($slots as $i => $slot) {
                // [$ip, $install, $click, $cr, $ipcr, $conv] = $sampleData[$slot];
                [$ip, $install, $click, $cr] = $sampleData[$slot];

                // Simulate slight variation per app
                $factor = 1 - ($appIndex * 0.1);
                $ip      = (int) round($ip      * $factor);
                $install = (int) round($install * $factor);
                $click   = (int) round($click   * $factor);

                // Interval = current - previous cumulative
                $intIp      = $prevCumulative ? max(0, $ip      - $prevCumulative[0]) : 0;
                $intInstall = $prevCumulative ? max(0, $install - $prevCumulative[1]) : 0;
                $intClick   = $prevCumulative ? max(0, $click   - $prevCumulative[2]) : 0;
                // $intCR      = $intClick > 0 ? round($intInstall / $intClick * 100, 2) / 100 : null;

                AppMetric::updateOrCreate(
                    ['app_id' => $app->id, 'report_date' => $date, 'time_slot' => $slot],
                    [
                        'ip_51la'                  => $ip,
                        'total_install'            => $install,
                        'total_click'              => $click,
                        'click_ratio'              => $cr,
                        // 'ip_click_ratio'           => $ipcr,
                        // 'conversion_rate'          => $conv,
                        'ip_click_ratio'           => $install > 0 ? ($click / $install) : 0,
                        'conversion_rate'          => $ip > 0 ? ($install / $ip)   : 0,

                        'interval_ip'              => $intIp,
                        'interval_install'         => $intInstall,
                        'interval_click'           => $intClick,
                        'interval_click_ratio'     => $intClick  > 0 ? round($intClick  / max($intIp, 1), 2) : null,
                        // 'interval_ip_click_ratio'  => $intIp     > 0 ? round($intClick  / $intIp, 2) : null,
                        // 'interval_conversion_rate' => $intCR,
                        'interval_ip_click_ratio'  => $intIp > 0  ? round($intClick / $intIp, 2) : 0,
                        'interval_conversion_rate' => $intInstall > 0 ? ($intInstall / $intIp) : 0,
                    ]
                );

                $prevCumulative = [$ip, $install, $click];
            }
        }

        $this->command->info('✅ Seeded ' . count($apps) . ' apps with metrics for ' . $date);
    }
}