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

class ReportController extends Controller
{

    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $apps = App::where('is_active', true)->orderBy('id')->get();

        return view('admin.pages.reports.index', compact('apps', 'date'));
    }

    public function data(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $apps = App::where('is_active', true)->orderBy('id')->get();

        // All 24 time slots (00:00 - 23:00)
        $allSlots = [];
        for ($h = 0; $h < 24; $h++) {
            $allSlots[] = sprintf('%02d:00', $h);
        }

        // Fetch all metrics for this date, keyed by [app_id][time_slot]
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
                    // 'ip_click_ratio' => $metric->interval_ip > 0 ? ($metric->interval_click / $metric->interval_ip) : 0,
                    // 'conversion_rate' => $metric->interval_ip > 0 ? ($metric->interval_install / $metric->interval_ip) : 0,
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
                    // 'ip_click_ratio' => $metric->interval_ip > 0 ? ($metric->interval_click / $metric->interval_ip) : 0,
                    // 'conversion_rate' => $metric->interval_ip > 0 ? ($metric->interval_install / $metric->interval_ip) : 0,
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

    public function export(Request $request)
    {
        $date     = $request->get('date', Carbon::today()->toDateString());
        $filename = 'app-metrics-' . $date . '.xlsx';

        return Excel::download(new ReportExport($date), $filename);
    }

}