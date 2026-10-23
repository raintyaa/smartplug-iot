<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PowerReading;
use App\Models\TemperatureReading;
use App\Services\FirebaseService;

class SensorController extends Controller
{
    public function index(FirebaseService $firebase)
    {
        $firebaseData = cache()->remember('firebase_system_data', 3, function () use ($firebase) {
            return $firebase->getSystemData();
        });

        $sensorUpdatedAt = (int) ($firebaseData['sensors']['updated_at'] ?? 0);
        $isEsp32Online = false;

        if ($sensorUpdatedAt > 1700000000) {
            $isEsp32Online = ((now()->timestamp - $sensorUpdatedAt) <= 75);
        } else {
            $latestPowerCheck = PowerReading::latest('recorded_at')->first();
            if ($latestPowerCheck && $latestPowerCheck->recorded_at && $latestPowerCheck->recorded_at->diffInSeconds(now()) <= 75) {
                $isEsp32Online = true;
            } elseif (cache()->get('esp32_last_heartbeat', false)) {
                $isEsp32Online = true;
            }
        }

        // Hanya catat telemetri ke database jika ESP32 aktif online
        if ($isEsp32Online && !empty($firebaseData['sensors'])) {
            $firebase->recordSensorReadings($firebaseData['sensors']);
            cache()->put('esp32_last_heartbeat', true, 75);
        }

        $latestPower = PowerReading::latest('recorded_at')->first();
        $latestTemp = TemperatureReading::latest('recorded_at')->first();

        // 30 data titik terakhir untuk grafik
        $powerHistory = PowerReading::latest('recorded_at')->take(30)->get()->reverse();
        $tempHistory = TemperatureReading::latest('recorded_at')->take(30)->get()->reverse();

        $powerChartLabels = $powerHistory->map(fn($r) => $r->recorded_at->format('H:i:s'))->values();
        $powerChartWatts = $powerHistory->map(fn($r) => $r->power)->values();
        $powerChartVolts = $powerHistory->map(fn($r) => $r->voltage)->values();
        $powerChartAmps = $powerHistory->map(fn($r) => $r->current)->values();

        $tempChartLabels = $tempHistory->map(fn($r) => $r->recorded_at->format('H:i:s'))->values();
        $tempChartValues = $tempHistory->map(fn($r) => $r->temperature)->values();
        $humidityChartValues = $tempHistory->map(fn($r) => $r->humidity)->values();

        return view('sensors.index', compact(
            'isEsp32Online',
            'latestPower',
            'latestTemp',
            'powerChartLabels',
            'powerChartWatts',
            'powerChartVolts',
            'powerChartAmps',
            'tempChartLabels',
            'tempChartValues',
            'humidityChartValues'
        ));
    }

    /**
     * API Ingest: Menerima data sensor dari ESP32 via HTTP POST
     * Endpoint: POST /api/sensors/ingest
     */
    public function apiIngest(Request $request, FirebaseService $firebase)
    {
        $validated = $request->validate([
            'voltage' => 'nullable|numeric',
            'current' => 'nullable|numeric',
            'power' => 'nullable|numeric',
            'energy' => 'nullable|numeric',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric',
        ]);

        if (isset($validated['voltage']) || isset($validated['power'])) {
            cache()->put('esp32_last_heartbeat', true, 75);
            PowerReading::create([
                'voltage' => $validated['voltage'] ?? 0,
                'current' => $validated['current'] ?? 0,
                'power' => $validated['power'] ?? 0,
                'energy' => $validated['energy'] ?? 0,
                'recorded_at' => now(),
            ]);
        }

        if (isset($validated['temperature'])) {
            TemperatureReading::create([
                'temperature' => $validated['temperature'],
                'humidity' => $validated['humidity'] ?? 0,
                'recorded_at' => now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sensor telemetry recorded successfully',
        ]);
    }
}
