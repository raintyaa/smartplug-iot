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

    public function index()
    {
        // 1. Sinkronkan transaksi dari Firebase ke database lokal & ambil data realtime
        $this->firebase->syncTransactions();
        $firebaseData = $this->firebase->getSystemData();
        if (!empty($firebaseData['sensors'])) {
            $this->firebase->recordSensorReadings($firebaseData['sensors']);
        }

        // 2. Kalkulasi Ringkasan Finansial Hari Ini
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

        $sensorUpdatedAt = (int) ($firebaseData['sensors']['updated_at'] ?? 0);
        $isEsp32Online = ($sensorUpdatedAt > 0) && ((now()->timestamp - $sensorUpdatedAt) <= 25);

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
    public function apiRealtime()
    {
        $data = $this->firebase->getSystemData();
        $sensorUpdatedAt = (int) ($data['sensors']['updated_at'] ?? 0);
        $isEsp32Online = ($sensorUpdatedAt > 0) && ((now()->timestamp - $sensorUpdatedAt) <= 25);

        // Rekam data sensor ke database lokal hanya jika ESP32 online
        if ($isEsp32Online && !empty($data['sensors'])) {
            $this->firebase->recordSensorReadings($data['sensors']);
        }

        // Ambil data sensor terbaru dari database
        $latestPower = PowerReading::latest('recorded_at')->first();
        $latestTemp = TemperatureReading::latest('recorded_at')->first();

        return response()->json([
            'slots' => [
                'slot1' => $data['slot1'] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
                'slot2' => $data['slot2'] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
                'slot3' => $data['slot3'] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
            ],
            'active_selection' => $data['active_selection'] ?? 'IDLE',
            'is_esp32_online' => $isEsp32Online,
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

        if ($success) {
            return back()->with('success', "Slot {$slotNumber} berhasil dimatikan secara manual.");
        }
        return back()->with('error', "Gagal menghubungi Firebase untuk mematikan Slot {$slotNumber}.");
    }

    /**
     * Emergency cutoff seluruh sistem
     */
    public function emergencyStop()
    {
        $this->firebase->triggerEmergencyStop();

        Transaction::where('status', 'active')
            ->update([
                'status' => 'emergency_stopped',
                'ended_at' => now(),
            ]);

        return back()->with('warning', 'EMERGENCY CUTOFF DIAKTIFKAN: Seluruh relay telah dimatikan seketika!');
    }
}
