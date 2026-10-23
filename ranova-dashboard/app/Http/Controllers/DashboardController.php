<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;
use App\Models\Transaction;
use App\Models\PowerReading;
use App\Models\TemperatureReading;
use App\Models\Setting;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    /**
     * Evaluasi ketahanan status online ESP32 dari multi-sumber (Unix Timestamp, Histori Telemetri MySQL, Cache Heartbeat)
     */
    protected function checkEsp32Online(array $data): bool
    {
        $sensorUpdatedAt = (int) ($data['sensors']['updated_at'] ?? 0);

        // 1. Jika updated_at adalah Unix Epoch valid (> 1700000000)
        if ($sensorUpdatedAt > 1700000000) {
            if ((now()->timestamp - $sensorUpdatedAt) <= 75) {
                return true;
            }
        }

        // 2. Jika ESP32 belum sync NTP (uptime seconds) atau nilai updated_at 0:
        // Cek apakah ada telemetri PZEM atau DHT22 yang baru tersimpan di MySQL dalam 75 detik terakhir
        $latestPower = PowerReading::latest('recorded_at')->first();
        if ($latestPower && $latestPower->recorded_at && $latestPower->recorded_at->diffInSeconds(now()) <= 75) {
            return true;
        }

        $latestTemp = TemperatureReading::latest('recorded_at')->first();
        if ($latestTemp && $latestTemp->recorded_at && $latestTemp->recorded_at->diffInSeconds(now()) <= 75) {
            return true;
        }

        // 3. Fallback: cek cache heartbeat dari ingest API / sensor updates
        return (bool) cache()->get('esp32_last_heartbeat', false);
    }

    public function index()
    {
        // 1. Ambil data state sistem (Gunakan cache 5 detik atau fallback langsung agar tidak blocking HTTP ke Firebase)
        $firebaseData = cache()->remember('firebase_system_data', 5, function () {
            return $this->firebase->getSystemData();
        });

        // 2. Kalkulasi Ringkasan Finansial Hari Ini langsung dari database MySQL lokal (< 5ms)
        $todayRevenue = Transaction::whereDate('created_at', Carbon::today())
            ->whereIn('status', ['completed', 'active'])
            ->sum('nominal_paid');

        $totalRevenue = Transaction::whereIn('status', ['completed', 'active'])
            ->sum('nominal_paid');

        // 3. Ambil data kWh terbaru dari PZEM
        $latestPower = PowerReading::latest('recorded_at')->first();
        $totalKwh = $latestPower ? (float) $latestPower->energy : (float) ($firebaseData['sensors']['energy'] ?? 0.0);

        // 4. Biaya PLN
        $plnTariff = (float) Setting::get('tariff_pln_per_kwh', 1444.70);
        $totalPlnCost = $totalKwh * $plnTariff;
        $netProfit = $totalRevenue - $totalPlnCost;

        // 5. Suhu terbaru dari DHT22
        $latestTemp = TemperatureReading::latest('recorded_at')->first();
        $overheatThreshold = (float) Setting::get('overheat_threshold_c', 42.0);

        $isEsp32Online = $this->checkEsp32Online($firebaseData);

        // 6. Transaksi aktif
        $activeTransactions = Transaction::where('status', 'active')->get();

        return view('dashboard', compact(
            'firebaseData',
            'isEsp32Online',
            'todayRevenue',
            'totalRevenue',
            'totalKwh',
            'totalPlnCost',
            'netProfit',
            'latestPower',
            'latestTemp',
            'overheatThreshold',
            'activeTransactions'
        ));
    }

    /**
     * Endpoint API JSON untuk update realtime di browser (polling setiap 2 detik)
     */
    public function apiRealtime(Request $request)
    {
        // 0. Sinkronkan transaksi dari Firebase ke database lokal jika diminta (?sync=1) atau tiap >=30s
        $lastSync = (int) cache()->get('last_tx_sync_time', 0);
        if ($request->boolean('sync') || (now()->timestamp - $lastSync >= 30)) {
            if (cache()->add('sync_tx_lock', 1, 10)) {
                $this->firebase->syncTransactions();
                cache()->put('last_tx_sync_time', now()->timestamp, 120);
                cache()->forget('sync_tx_lock');
            }
        }

        // Cache ringan 2 detik agar request berulang tidak men-choke single-threaded PHP server
        $data = cache()->remember('firebase_system_data', 2, function () {
            return $this->firebase->getSystemData();
        });

        $sensorUpdatedAt = (int) ($data['sensors']['updated_at'] ?? 0);
        $isEsp32Online = $this->checkEsp32Online($data);

        // Rekam data sensor ke database lokal hanya jika ESP32 online
        if ($isEsp32Online && !empty($data['sensors'])) {
            $this->firebase->recordSensorReadings($data['sensors']);
            cache()->put('esp32_last_heartbeat', true, 75);
        }

        // Ambil data sensor terbaru dari database
        $latestPower = PowerReading::latest('recorded_at')->first();
        $latestTemp = TemperatureReading::latest('recorded_at')->first();

        // 1. Ekstrak data slot
        $slots = [
            'slot1' => $data['slot1'] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
            'slot2' => $data['slot2'] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
            'slot3' => $data['slot3'] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
        ];

        // 2. Jika ESP32 offline (steker dicabut / putus koneksi), tandai seluruh slot sebagai OFFLINE
        // untuk mengeliminasi ghost active slot dan timer palsu di browser
        if (!$isEsp32Online) {
            foreach ($slots as $k => &$s) {
                $s['status'] = 'OFFLINE';
            }
            unset($s);
        }

        // 3. Kalkulasi Ringkasan Finansial Realtime
        $todayRevenue = Transaction::whereDate('created_at', Carbon::today())
            ->whereIn('status', ['completed', 'active'])
            ->sum('nominal_paid');

        $totalRevenue = Transaction::whereIn('status', ['completed', 'active'])
            ->sum('nominal_paid');

        $totalKwh = $latestPower ? (float) $latestPower->energy : (float) ($data['sensors']['energy'] ?? 0.0);
        $plnTariff = (float) Setting::get('tariff_pln_per_kwh', 1444.70);
        $totalPlnCost = $totalKwh * $plnTariff;
        $netProfit = $totalRevenue - $totalPlnCost;

        return response()->json([
            'slots' => $slots,
            'active_selection' => $data['active_selection'] ?? 'IDLE',
            'is_esp32_online' => $isEsp32Online,
            'financials' => [
                'today_revenue' => (int) $todayRevenue,
                'total_revenue' => (int) $totalRevenue,
                'total_kwh' => (float) $totalKwh,
                'total_pln_cost' => (int) round($totalPlnCost),
                'net_profit' => (int) round($netProfit),
            ],
            'sensors' => [
                'temperature' => $isEsp32Online ? (float) ($data['sensors']['temperature'] ?? ($latestTemp->temperature ?? 0.0)) : 0.0,
                'humidity' => $isEsp32Online ? (float) ($data['sensors']['humidity'] ?? ($latestTemp->humidity ?? 0.0)) : 0.0,
                'voltage' => $isEsp32Online ? (float) ($data['sensors']['voltage'] ?? ($latestPower->voltage ?? 0.0)) : 0.0,
                'current' => $isEsp32Online ? (float) ($data['sensors']['current'] ?? ($latestPower->current ?? 0.0)) : 0.0,
                'power' => $isEsp32Online ? (float) ($data['sensors']['power'] ?? ($latestPower->power ?? 0.0)) : 0.0,
                'energy' => (float) ($data['sensors']['energy'] ?? ($latestPower->energy ?? 0.0)),
                'updated_at' => $sensorUpdatedAt,
            ],
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * Tindakan manual aktivasi slot oleh operator (Bypass Bayar & Mode Kontinu)
     */
    public function manualStartSlot(Request $request, int $slotNumber)
    {
        $success = $this->firebase->manualStartSlot($slotNumber);

        // Buat record audit transaksi bypass
        Transaction::create([
            'slot_number' => $slotNumber,
            'nominal_paid' => 0,
            'duration_seconds' => 0,
            'status' => 'active',
            'payment_reference' => 'ADMIN-MANUAL-' . strtoupper(uniqid()),
            'customer_name' => 'Operator / Admin',
            'started_at' => now(),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => $success
                    ? "Slot {$slotNumber} berhasil DINYALAKAN (Mode Kontinu / Tanpa Batas Waktu)."
                    : "Gagal menghubungi Firebase untuk menyalakan Slot {$slotNumber}."
            ], $success ? 200 : 500);
        }

        if ($success) {
            return back()->with('success', "Slot {$slotNumber} berhasil DINYALAKAN (Mode Kontinu / Tanpa Batas Waktu).");
        }
        return back()->with('error', "Gagal menghubungi Firebase untuk menyalakan Slot {$slotNumber}.");
    }

    /**
     * Tindakan manual stop slot oleh operator
     */
    public function forceStopSlot(Request $request, int $slotNumber)
    {
        $success = $this->firebase->forceStopSlot($slotNumber);

        // Update record transaksi jika ada yang aktif
        Transaction::where('slot_number', $slotNumber)
            ->where('status', 'active')
            ->update([
                'status' => 'force_stopped',
                'ended_at' => now(),
            ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => $success
                    ? "Slot {$slotNumber} berhasil dimatikan secara manual."
                    : "Gagal menghubungi Firebase untuk mematikan Slot {$slotNumber}."
            ], $success ? 200 : 500);
        }

        if ($success) {
            return back()->with('success', "Slot {$slotNumber} berhasil dimatikan secara manual.");
        }
        return back()->with('error', "Gagal menghubungi Firebase untuk mematikan Slot {$slotNumber}.");
    }

    /**
     * Emergency cutoff seluruh sistem
     */
    public function emergencyStop(Request $request)
    {
        $this->firebase->triggerEmergencyStop();

        Transaction::where('status', 'active')
            ->update([
                'status' => 'emergency_stopped',
                'ended_at' => now(),
            ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'EMERGENCY CUTOFF DIAKTIFKAN: Seluruh relay telah dimatikan seketika!'
            ]);
        }

        return back()->with('warning', 'EMERGENCY CUTOFF DIAKTIFKAN: Seluruh relay telah dimatikan seketika!');
    }
}
