<?php

use App\Http\Controllers\admin\AdminDashboardController;
use App\Http\Controllers\admin\AppController;
use App\Http\Controllers\admin\AppMetricController;
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

    // Store / update a metric
    Route::post('/report/metrics', [ReportController::class, 'store'])->name('report.store');
    
    //Role and User Section
    Route::resource('roles', RoleController::class);
    Route::resource('users', UserController::class);

});

require __DIR__.'/auth.php';
