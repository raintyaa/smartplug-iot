<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Transaction;
use App\Models\PowerReading;
use App\Models\TemperatureReading;

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
            // Update kedua node untuk kompatibilitas penuh webhook & firmware
            Http::timeout(4)->patch("{$this->databaseUrl}/slots/slot{$slotNumber}.json", $data);
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
     * Aktivasi manual stop kontak oleh Admin (Tanpa bayar & Tanpa batas waktu / Kontinu)
     */
    public function manualStartSlot(int $slotNumber): bool
    {
        $now = now()->timestamp;
        return $this->updateSlot($slotNumber, [
            'status' => 'ACTIVE',
            'active_duration' => 0,     // 0 = Mode tanpa batas waktu (kontinu sampai dimatikan manual)
            'duration_seconds' => 0,    // 0 = Mode tanpa batas waktu
            'started_at' => $now,
            'nominal_paid' => 0,
            'amount_paid' => 0,
            'activated_at' => $now,
            'expires_at' => 0,          // Tidak kedaluwarsa otomatis
            'mode' => 'MANUAL_OVERRIDE',
        ]);
    }

    /**
     * Force Stop slot (matikan sewa lebih awal oleh admin)
     */
    public function forceStopSlot(int $slotNumber): bool
    {
        return $this->updateSlot($slotNumber, [
            'status' => 'STANDBY',
            'active_duration' => 0,
            'duration_seconds' => 0,
            'started_at' => 0,
            'nominal_paid' => 0,
            'amount_paid' => 0,
            'activated_at' => 0,
            'expires_at' => 0,
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
     * Sinkronkan riwayat transaksi dari Firebase RTDB ke database MySQL lokal
     */
     public function syncTransactions(): int
     {
         try {
             $response = Http::timeout(4)->get("{$this->databaseUrl}/transactions.json");
             if (!$response->successful() || empty($response->json())) {
                 return 0;
             }

             // Ambil status live slot saat ini dari Firebase untuk sinkronisasi akurat
             $slotsResponse = Http::timeout(3)->get("{$this->databaseUrl}/slots.json");
             $liveSlots = ($slotsResponse->successful()) ? ($slotsResponse->json() ?? []) : [];

             $transactions = $response->json();
             $syncedCount = 0;

             // Identifikasi transaksi terbaru untuk tiap slot dari data push keys Firebase
             $latestTxKeyPerSlot = [];
             foreach ($transactions as $key => $item) {
                 if (!is_array($item)) continue;
                 $sNum = (int) str_replace('slot', '', $item['slot'] ?? '1');
                 if ($sNum >= 1 && $sNum <= 3) {
                     $latestTxKeyPerSlot[$sNum] = $key;
                 }
             }

             foreach ($transactions as $key => $item) {
                 if (!is_array($item)) continue;

                 $slotNumber = (int) str_replace('slot', '', $item['slot'] ?? '1');
                 if ($slotNumber < 1 || $slotNumber > 3) $slotNumber = 1;

                 $nominal = (int) ($item['amount'] ?? 1000);
                 $duration = (int) ($item['duration_sec'] ?? 900);
                 $paidAtMs = $item['paid_at'] ?? null;
                 $startedAt = $paidAtMs ? Carbon::createFromTimestampMs($paidAtMs) : now();
                 $endedAt = (clone $startedAt)->addSeconds($duration);

                 // Cek apakah slot fisik ini sedang aktif di Firebase
                 $slotKey = 'slot' . $slotNumber;
                 $isSlotCurrentlyActive = false;
                 if (isset($liveSlots[$slotKey])) {
                     $slotStatus = strtoupper($liveSlots[$slotKey]['status'] ?? '');
                     $isSlotCurrentlyActive = ($slotStatus === 'ACTIVE' || $slotStatus === 'PAUSED_OVERHEAT');
                 }

                 // Jika transaksi ini adalah transaksi terbaru untuk slot tersebut dan slot fisik masih ACTIVE,
                 // maka statusnya tetap 'active' dan tidak diubah prematur menjadi 'completed'
                 $isLatestForSlot = isset($latestTxKeyPerSlot[$slotNumber]) && $latestTxKeyPerSlot[$slotNumber] === $key;
                 if ($isLatestForSlot && $isSlotCurrentlyActive) {
                     $status = 'active';
                 } else {
                     $status = ($endedAt->isPast()) ? 'completed' : 'active';
                 }

                 $ref = $item['event_raw'] ?? $key;

                 $tx = Transaction::updateOrCreate(
                     ['payment_reference' => $ref],
                     [
                         'slot_number' => $slotNumber,
                         'nominal_paid' => $nominal,
                         'duration_seconds' => $duration,
                         'started_at' => $startedAt,
                         'ended_at' => $endedAt,
                         'status' => $status,
                         'customer_name' => 'Pengguna QRIS',
                         'created_at' => $startedAt,
                         'updated_at' => now(),
                     ]
                 );

                 // Pastikan created_at mencerminkan waktu pembayaran asli
                 if ($tx->created_at && $tx->created_at->timestamp !== $startedAt->timestamp) {
                     $tx->created_at = $startedAt;
                     $tx->save();
                 }

                 $syncedCount++;
             }

             return $syncedCount;
         } catch (\Exception $e) {
             Log::warning("Firebase transaction sync error: " . $e->getMessage());
             return 0;
         }
     }

    /**
     * Simpan data sensor terbaru dari Firebase ke database MySQL lokal
     */
    public function recordSensorReadings(array $sensorData): void
    {
        if (empty($sensorData)) return;

        $voltage = (float) ($sensorData['voltage'] ?? 0);
        $current = (float) ($sensorData['current'] ?? 0);
        $power = (float) ($sensorData['power'] ?? 0);
        $energy = (float) ($sensorData['energy'] ?? 0);
        $temp = (float) ($sensorData['temperature'] ?? 0);
        $humidity = (float) ($sensorData['humidity'] ?? 0);
        $updatedAt = isset($sensorData['updated_at']) && $sensorData['updated_at'] > 0
            ? Carbon::createFromTimestamp($sensorData['updated_at'])
            : now();

        // Hanya catat jika data valid
        if ($voltage > 0 || $power > 0 || $energy > 0) {
            $lastPower = PowerReading::latest('recorded_at')->first();
            if (!$lastPower || $lastPower->recorded_at->diffInSeconds($updatedAt) >= 10) {
                PowerReading::create([
                    'voltage' => $voltage,
                    'current' => $current,
                    'power' => $power,
                    'energy' => $energy,
                    'frequency' => 50.0,
                    'power_factor' => 1.0,
                    'recorded_at' => $updatedAt,
                ]);
            }
        }

        if ($temp > 0) {
            $lastTemp = TemperatureReading::latest('recorded_at')->first();
            if (!$lastTemp || $lastTemp->recorded_at->diffInSeconds($updatedAt) >= 10) {
                TemperatureReading::create([
                    'temperature' => $temp,
                    'humidity' => $humidity,
                    'recorded_at' => $updatedAt,
                ]);
            }
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
