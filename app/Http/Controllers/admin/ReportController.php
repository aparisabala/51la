<?php

namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\AppMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Exports\ReportExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Show main report page
     */
    public function index(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());

        $apps = App::where('is_active', true)->orderBy('id')->get();

        return view('admin.pages.reports.index', compact('apps', 'date'));
    }

    /**
     * Return JSON data for DataTable (AJAX)
     *
     * Response format:
     * Each time slot returns TWO rows:
     *   1. white row  → cumulative data
     *   2. orange row → interval (diff) data
     */
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

    /**
     * Store/update a single metric entry
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'app_id'                    => 'required|exists:apps,id',
            'report_date'               => 'required|date',
            'time_slot'                 => 'required|string',
            'ip_51la'                   => 'nullable|integer',
            'total_install'             => 'nullable|integer',
            'total_click'               => 'nullable|integer',
            'click_ratio'               => 'nullable|numeric',
            'ip_click_ratio'            => 'nullable|numeric',
            'conversion_rate'           => 'nullable|numeric',
            'interval_ip'               => 'nullable|integer',
            'interval_install'          => 'nullable|integer',
            'interval_click'            => 'nullable|integer',
            'interval_click_ratio'      => 'nullable|numeric',
            'interval_ip_click_ratio'   => 'nullable|numeric',
            'interval_conversion_rate'  => 'nullable|numeric',
        ]);

        AppMetric::updateOrCreate(
            [
                'app_id'      => $validated['app_id'],
                'report_date' => $validated['report_date'],
                'time_slot'   => $validated['time_slot'],
            ],
            $validated
        );

        return response()->json(['success' => true]);
    }

    public function export(Request $request)
    {
        $date     = $request->get('date', Carbon::today()->toDateString());
        $filename = 'app-metrics-' . $date . '.xlsx';

        return Excel::download(new ReportExport($date), $filename);
    }
}