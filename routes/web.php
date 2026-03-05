<?php

use App\Http\Controllers\admin\AdminDashboardController;
use App\Http\Controllers\admin\AppController;
use App\Http\Controllers\admin\AppMetricController;
use App\Http\Controllers\admin\SettingsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\admin\ReportController;


Route::get('/', function () {
    return view('auth.login');
});

Route::middleware('auth')->group(callback: function () {

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/unauthorized-action', [AdminDashboardController::class, 'unauthorized'])->name('unauthorized.action');


    //Apps Section
    Route::get('/apps-section', [AppController::class, 'index'])->name('apps.section');
    Route::post('/apps-store', [AppController::class, 'store'])->name('apps.store');
    Route::put('/apps-update/{id}', [AppController::class, 'update'])->name('apps.update');
    Route::get('/apps-delete/{id}', [AppController::class, 'destroy'])->name('apps.destroy');


    // Report page
    Route::get('/report', [ReportController::class, 'index'])->name('report.index');

    // AJAX data endpoint
    Route::get('/report/data', [ReportController::class, 'data'])->name('report.data');

    // Excel export
    Route::get('/report/export', [ReportController::class, 'export'])->name('report.export');

    // fetch today data
    Route::get('/report/fetch', [AppMetricController::class, 'fetch'])->name('report.fetch');

    // metrics ip entry
    Route::get('/metrics/manual-ip',  [AppMetricController::class, 'manualIpForm'])->name('metrics.manual-ip.form');
    Route::post('/metrics/manual-ip', [AppMetricController::class, 'manualIpEntry'])->name('metrics.manual-ip.store');
    Route::get('/metrics/manual-ip/existing', [AppMetricController::class, 'getExistingIp'])->name('metrics.manual-ip.existing');

    // settings
    Route::get('/settings',  [SettingsController::class, 'index'])->name('settings.section');
    Route::post('/settings-change', [SettingsController::class, 'change'])->name('settings.change');


    //Role and User Section
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);

});

require __DIR__.'/auth.php';
