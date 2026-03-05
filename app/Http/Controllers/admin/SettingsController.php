<?php

namespace App\Http\Controllers\admin;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all();
        $active_data = Setting::where('is_active', 1)->first();
        return view('admin.pages.settings.index', compact('settings', 'active_data'));
    }

    public function change(Request $request)
    {
        Setting::query()->update(['is_active' => 0]);

        $setting = Setting::findOrFail($request->time_difference_id);

        $setting->update([
            'is_active' => 1,
        ]);

        return redirect()->route('settings.section')->with('success', 'Active setting updated successfully!');
    }

}