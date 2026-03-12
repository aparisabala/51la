<?php

namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppMetric;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exports\ReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{

    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $apps = App::where('is_active', true)->orderBy('id', 'ASC')->get();
        $setting = Setting::where('is_active', 1)->first();
        $app_metric = AppMetric::where('report_date', $date)->latest()->first();

        if(empty($app_metric)){
            $time_slot = Carbon::now()->format('H:i');
        }else{

            $appCount = $apps->count();

            $latestTime = AppMetric::where('report_date', $date)
                ->latest('time_slot')
                ->value('time_slot');

            $latestTimeCount = AppMetric::where('report_date', $date)
                ->where('time_slot', $latestTime)
                ->distinct('app_id')
                ->count('app_id');

            if ($latestTimeCount >= $appCount) {
                $time_slot = Carbon::parse($latestTime)->addMinutes((int) $setting->time_difference)->format('H:i');
            } else {
                $time_slot = Carbon::parse($latestTime)->format('H:i');
            }

        }

        return view('admin.pages.reports.index', compact('apps', 'date', 'setting', 'time_slot'));
    }

    public function data(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $apps = App::where('is_active', true)->orderBy('id')->get();
        
        $allSlots = AppMetric::where('report_date', $date)
            ->distinct()
            ->orderBy('time_slot')
            ->pluck('time_slot')
            ->toArray();

        $metricsRaw = AppMetric::where('report_date', $date)
            ->get()
            ->groupBy('app_id')
            ->map(fn($group) => $group->keyBy('time_slot'));

        $rows = [];

        foreach ($allSlots as $slot) {
            // ── White row (cumulative) ──────────────────────────
            $whiteRow = [
                'time'     => $slot,
                'row_type' => 'cumulative',  // white background
                'apps'     => [],
            ];

            // ── Orange row (interval) ───────────────────────────
            $orangeRow = [
                'time'     => '小时段',
                'row_type' => 'interval',    // orange background
                'apps'     => [],
            ];

            foreach ($apps as $app) {
                $metric = $metricsRaw[$app->id][$slot] ?? null;

                // White row data
                $whiteRow['apps'][$app->id] = $metric ? [
                    'ip_51la'          => number_format($metric->ip_51la),
                    'total_install'    => number_format($metric->total_install),
                    'total_click'      => number_format($metric->total_click),
                    'click_ratio'      => $metric->click_ratio ?? '-',
                    'ip_click_ratio'   => $metric->ip_click_ratio ?? '-',
                    'conversion_rate'  => $metric->conversion_rate_display,
                ] : [
                    'ip_51la'         => '-',
                    'total_install'   => '-',
                    'total_click'     => '-',
                    'click_ratio'     => '-',
                    'ip_click_ratio'  => '-',
                    'conversion_rate' => '-',
                ];

                // Orange row data (interval)
                $orangeRow['apps'][$app->id] = $metric ? [
                    'ip_51la'         => number_format($metric->interval_ip),
                    'total_install'   => number_format($metric->interval_install),
                    'total_click'     => number_format($metric->interval_click),
                    'click_ratio'     => $metric->interval_click_ratio ?? '-',
                    'ip_click_ratio'  => $metric->interval_ip_click_ratio ?? '-',
                    'conversion_rate' => $metric->interval_conversion_rate_display,
                ] : [
                    'ip_51la'         => '-',
                    'total_install'   => '-',
                    'total_click'     => '-',
                    'click_ratio'     => '-',
                    'ip_click_ratio'  => '-',
                    'conversion_rate' => '-',
                ];
            }

            $rows[] = $whiteRow;
            $rows[] = $orangeRow;
        }

        return response()->json([
            'apps' => $apps->map(fn($a) => ['id' => $a->id, 'name' => $a->name]),
            'rows' => $rows,
        ]);
    }

    public function store(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $app_id = $request->get('app_id');
        $time = Carbon::parse($request->get('time'))->format('H:i');

        $app = App::find($app_id);

        try {
            // $response = Http::withToken($app->api_key)->get($app->api_url, ['date' => $date]);
            // if ($response->failed()) {
            //     continue; 
            // }
            // $apiData = $response->json(); 
            $apiData = [
                $time => [
                    $request->get('ip', 6511),       
                    $request->get('install', 8419),  
                    $request->get('click', 17262),   
                ],
            ];

        } catch (\Exception $e) {
            \Log::error("API Failed for App {$app}: " . $e->getMessage());
        }

        foreach ($apiData as $slot => $metrics) {
            [$ip, $install, $click] = $metrics;

            // Get the most recent PREVIOUS record for this app on this date
            $prev = AppMetric::where('app_id', $app->id)
                ->where('report_date', $date)
                ->where('time_slot', '<', $slot)
                ->orderBy('time_slot', 'desc')
                ->first();

            // If no previous record exists, this is the first slot of the day
            // — treat the entire value as the interval too
            $prevIp      = $prev?->ip_51la       ?? 0;
            $prevInstall = $prev?->total_install  ?? 0;
            $prevClick   = $prev?->total_click    ?? 0;

            $intIp      = max(0, $ip      - $prevIp);
            $intInstall = max(0, $install - $prevInstall);
            $intClick   = max(0, $click   - $prevClick);

            AppMetric::updateOrCreate(
                [
                    'app_id'      => $app_id,
                    'report_date' => $date,
                    'time_slot'   => $slot,
                ],
                [
                    'ip_51la'       => $ip,
                    'total_install' => $install,
                    'total_click'   => $click,

                    'click_ratio'     => $install > 0 ? round($click   / $install, 2) : 0,
                    'ip_click_ratio'  => $ip > 0      ? round($click   / $ip, 2)      : 0,
                    'conversion_rate' => $ip > 0      ? round($install / $ip, 4)      : 0,

                    'interval_ip'      => $intIp,
                    'interval_install' => $intInstall,
                    'interval_click'   => $intClick,

                    'interval_click_ratio'     => $intInstall > 0 ? round($intClick   / $intInstall, 2) : 0,
                    'interval_ip_click_ratio'  => $intIp > 0      ? round($intClick   / $intIp, 2)      : 0,
                    'interval_conversion_rate' => $intIp > 0      ? round($intInstall / $intIp, 4)      : 0,
                ]
            );
        }

        return redirect()->back()->with('success', 'Metrics updated successfully!');
    }

    public function export(Request $request)
    {
        $date     = $request->get('date', Carbon::today()->toDateString());
        $filename = 'app-metrics-' . $date . '.xlsx';

        return Excel::download(new ReportExport($date), $filename);
    }

}