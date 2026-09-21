<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\SettingController;

// 1. Guest / Autentikasi
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// 2. Redirect root ke dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
});

// 3. Halaman Operator Terproteksi (Wajib Login)
Route::middleware('auth')->group(function () {
    // Dashboard Utama
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/realtime', [DashboardController::class, 'apiRealtime'])->name('dashboard.realtime');
    Route::post('/dashboard/slots/{slot}/stop', [DashboardController::class, 'forceStopSlot'])->name('dashboard.slots.stop');
    Route::post('/dashboard/emergency-stop', [DashboardController::class, 'emergencyStop'])->name('dashboard.emergency_stop');

    // Riwayat Transaksi
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Laporan Finansial
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    // Telemetri Sensor
    Route::get('/sensors', [SensorController::class, 'index'])->name('sensors.index');

    // Pengaturan Sistem
    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
});

// 4. Endpoint Ingest Telemetri dari ESP32 (Bebas CSRF)
Route::post('/api/sensors/ingest', [SensorController::class, 'apiIngest'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
