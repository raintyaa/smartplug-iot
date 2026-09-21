<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PowerReading;
use App\Models\TemperatureReading;
use App\Services\FirebaseService;

class SensorController extends Controller
{
    public function index()
    {
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
