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

class AppMetricController extends Controller
{

    public function fetch(Request $request)
    {
        $date = $request->get('date', Carbon::today()->toDateString());
        $time = $request->get('time');

        $apps = App::where('is_active', 1)->get();

        foreach ($apps as $app) {

            try {
                // $response = Http::withToken($app->api_key)->get($app->api_url, ['date' => $date]);

                // if ($response->failed()) {
                //     continue; 
                // }

                // $apiData = $response->json(); 

                
                $apiData = [
                    
                    $time => [4419,  10262],
                  
                ];
                
                
            } catch (\Exception $e) {
                \Log::error("API Failed for App {$app->id}: " . $e->getMessage());
                continue;
            }


            $prevCumulative = null;

            foreach ($apiData as $slot => $metrics) {
                [$install, $click] = $metrics;

                $ip = AppMetric::where('report_date', $date)->where('time_slot', $time)->value('ip_51la');
   
                $intIp      = $prevCumulative ? max(0, $ip - $prevCumulative[0]) : 0;
                $intInstall = $prevCumulative ? max(0, $install - $prevCumulative[1]) : 0;
                $intClick   = $prevCumulative ? max(0, $click - $prevCumulative[2]) : 0;

                

                    AppMetric::where('app_id', $app->id)
                            ->where('report_date', $date)
                            ->where('time_slot', $slot)
                            ->where('ip_51la', $ip)
                            ->update([
                                'total_install'            => $install,
                                'total_click'              => $click,
                                'click_ratio'              => $install > 0 ? ($click / $install) : 0,
                                'ip_click_ratio'           => $ip > 0 ? ($click / $ip) : 0,
                                'conversion_rate'          => $ip > 0 ? ($install / $ip) : 0,

                                'interval_install'         => $intInstall,
                                'interval_click'           => $intClick,
                                'interval_click_ratio'     => $intInstall > 0 ? round($intClick / $intInstall, 2) : 0,
                                'interval_ip_click_ratio'  => $intIp > 0 ? round($intClick / $intIp, 2) : 0,
                                'interval_conversion_rate' => $intIp > 0 ? ($intInstall / $intIp) : 0,
                            ]);
               
                $prevCumulative = [$ip, $install, $click];
            }
        }

        return redirect()->back()->with('success', 'Metrics updated successfully!');
    }

    public function manualIpForm()
    {
        $apps = App::where('is_active', 1)->get();
        $active_time_slot = Setting::where('is_active', 1)->first();
        return view('admin.pages.app_metrics.index', compact('apps', 'active_time_slot'));
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