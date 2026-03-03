<?php

namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exports\ReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Http;

class AppMetricController extends Controller
{

    public function fetch(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $apps = App::where('is_active', 1)->get();

        foreach ($apps as $app) {

            try {
                // $response = Http::withToken($app->api_key)->get($app->api_url, ['date' => $date]);

                // if ($response->failed()) {
                //     continue; 
                // }

                // $apiData = $response->json(); 

                $apiData = [
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
               
                
            } catch (\Exception $e) {
                \Log::error("API Failed for App {$app->id}: " . $e->getMessage());
                continue;
            }

            $prevCumulative = null;

            foreach ($apiData as $slot => $metrics) {
                [$ip, $install, $click, $cr] = $metrics;

   
                $intIp      = $prevCumulative ? max(0, $ip - $prevCumulative[0]) : 0;
                $intInstall = $prevCumulative ? max(0, $install - $prevCumulative[1]) : 0;
                $intClick   = $prevCumulative ? max(0, $click - $prevCumulative[2]) : 0;

                AppMetric::updateOrCreate(
                    ['app_id' => $app->id, 'report_date' => $date, 'time_slot' => $slot],
                    [
                        'ip_51la'                  => $ip,
                        'total_install'            => $install,
                        'total_click'              => $click,
                        'click_ratio'              => $cr,
                        'ip_click_ratio'           => $install > 0 ? ($click / $install) : 0,
                        'conversion_rate'          => $ip > 0 ? ($install / $ip) : 0,

                        'interval_ip'              => $intIp,
                        'interval_install'         => $intInstall,
                        'interval_click'           => $intClick,
                        'interval_click_ratio'     => $intIp > 0 ? round($intClick / $intIp, 2) : 0,
                        'interval_ip_click_ratio'  => $intIp > 0 ? round($intClick / $intIp, 2) : 0,
                        'interval_conversion_rate' => $intIp > 0 ? ($intInstall / $intIp) : 0,
                    ]
                );

                $prevCumulative = [$ip, $install, $click];
            }
        }

        return redirect()->back()->with('success', 'Metrics updated successfully!');
    }

    public function manualIpForm()
    {
        $apps = App::where('is_active', 1)->get();
        return view('admin.pages.app_metrics.index', compact('apps'));
    }
    
    public function manualIpEntry(Request $request)
    {
        $request->validate([
            'app_id'           => 'required|exists:apps,id',
            'date'             => 'required|date',
            'metrics'          => 'required|array',
            'metrics.*.time_slot' => 'required|string',
            'metrics.*.ip'        => 'required|numeric|min:0',
        ]);

        $app     = App::findOrFail($request->app_id);
        $date    = $request->date;
        $metrics = $request->metrics;

        usort($metrics, fn($a, $b) => strcmp($a['time_slot'], $b['time_slot']));

        $prevIp = null;

        foreach ($metrics as $row) {
            $slot    = $row['time_slot'];
            $ip      = (float) $row['ip'];
            $intIp   = $prevIp !== null ? max(0, $ip - $prevIp) : 0;

            AppMetric::updateOrCreate(
                ['app_id' => $app->id, 'report_date' => $date, 'time_slot' => $slot],
                [
                    'ip_51la'     => $ip,
                    'interval_ip' => $intIp,
                ]
            );

            $prevIp = $ip;
        }

        return redirect()->back()->with('success', 'IP metrics saved successfully!');
    }

    public function getExistingIp(Request $request)
    {
        $request->validate([
            'app_id' => 'required|exists:apps,id',
            'date'   => 'required|date',
        ]);

        $metrics = AppMetric::where('app_id', $request->app_id)
            ->where('report_date', $request->date)
            ->orderBy('time_slot')
            ->get(['time_slot', 'ip_51la']);

        return response()->json($metrics);
    }
}