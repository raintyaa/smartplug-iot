<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected string $databaseUrl;

    public function __construct()
    {
        $this->databaseUrl = rtrim(config('services.firebase.database_url', env('FIREBASE_DATABASE_URL', 'https://smartplug-4442d-default-rtdb.asia-southeast1.firebasedatabase.app')), '/');
    }

    /**
     * Ambil seluruh data state dari Firebase RTDB
     */
    public function getSystemData(): array
    {
        try {
            $response = Http::timeout(4)->get("{$this->databaseUrl}/.json");
            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception $e) {
            Log::warning("Firebase fetch error: " . $e->getMessage());
        }

        return $this->getDefaultFallbackData();
    }

    /**
     * Ambil data slot tertentu (slot1, slot2, slot3)
     */
    public function getSlot(int $slotNumber): array
    {
        try {
            $response = Http::timeout(3)->get("{$this->databaseUrl}/slot{$slotNumber}.json");
            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception $e) {
            Log::warning("Firebase getSlot{$slotNumber} error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Update status slot (misal Manual Stop oleh Admin)
     */
    public function updateSlot(int $slotNumber, array $data): bool
    {
        try {
            $response = Http::timeout(4)->patch("{$this->databaseUrl}/slot{$slotNumber}.json", $data);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Firebase updateSlot{$slotNumber} error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update konfigurasi tarif sewa dinamis ke Firebase RTDB
     */
    public function updatePricingConfig(int $basePrice, int $baseDurationSeconds): bool
    {
        try {
            $response = Http::timeout(4)->patch("{$this->databaseUrl}/config/pricing.json", [
                'base_price' => $basePrice,
                'base_duration_seconds' => $baseDurationSeconds,
                'updated_at' => now()->timestamp,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error("Firebase updatePricingConfig error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil konfigurasi tarif sewa dari Firebase RTDB
     */
    public function getPricingConfig(): array
    {
        try {
            $response = Http::timeout(3)->get("{$this->databaseUrl}/config/pricing.json");
            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Exception $e) {
            Log::warning("Firebase getPricingConfig error: " . $e->getMessage());
        }
        return ['base_price' => 1000, 'base_duration_seconds' => 900];
    }

    /**
     * Force Stop slot (matikan sewa lebih awal oleh admin)
     */
    public function forceStopSlot(int $slotNumber): bool
    {
        return $this->updateSlot($slotNumber, [
            'status' => 'STANDBY',
            'active_duration' => 0,
            'started_at' => 0,
            'nominal_paid' => 0,
        ]);
    }

    /**
     * Trigger Emergency Cutoff dari Web
     */
    public function triggerEmergencyStop(): bool
    {
        try {
            for ($i = 1; $i <= 3; $i++) {
                $this->forceStopSlot($i);
            }
            Http::timeout(3)->patch("{$this->databaseUrl}/.json", [
                'active_selection' => 'IDLE',
                'emergency_alert' => true,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error("Firebase emergency stop error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback data jika Firebase offline / belum ada koneksi
     */
    protected function getDefaultFallbackData(): array
    {
        return [
            'slot1' => ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
            'slot2' => ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
            'slot3' => ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0],
            'active_selection' => 'IDLE',
            'sensors' => [
                'temperature' => 0.0,
                'humidity' => 0.0,
                'voltage' => 0.0,
                'current' => 0.0,
                'power' => 0.0,
                'energy' => 0.0,
            ]
        ];
    }
}
