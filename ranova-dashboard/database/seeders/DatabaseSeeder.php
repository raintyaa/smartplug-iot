<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\PowerReading;
use App\Models\TemperatureReading;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Akun Admin Operator
        User::updateOrCreate(
            ['email' => 'admin@ranova.id'],
            [
                'name' => 'Operator RANOVA',
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Pengaturan Default Sistem
        $settings = [
            ['key' => 'tariff_pln_per_kwh', 'value' => '1444.70', 'label' => 'Tarif Dasar Listrik PLN (Rp/kWh)', 'description' => 'Tarif listrik golongan R-1/TR 1.300 VA'],
            ['key' => 'rental_price_per_15min', 'value' => '1000', 'label' => 'Tarif Sewa per 15 Menit (Rp)', 'description' => 'Harga sewa stop kontak per interval 15 menit'],
            ['key' => 'overheat_threshold_c', 'value' => '60.0', 'label' => 'Ambang Batas Suhu Bahaya (°C)', 'description' => 'Suhu maksimum internal box sebelum emergency shutdown'],
            ['key' => 'qris_timeout_seconds', 'value' => '60', 'label' => 'Timeout Menunggu Pembayaran (Detik)', 'description' => 'Waktu tunggu pembayaran QRIS sebelum slot di-reset ke standby'],
        ];

        foreach ($settings as $s) {
            Setting::updateOrCreate(['key' => $s['key']], $s);
        }

        // 3. Data Awal Riwayat Transaksi (Contoh Pengujian)
        if (Transaction::count() === 0) {
            Transaction::create([
                'slot_number' => 1,
                'nominal_paid' => 1000,
                'duration_seconds' => 900,
                'started_at' => Carbon::now()->subHours(2),
                'ended_at' => Carbon::now()->subHours(2)->addMinutes(15),
                'status' => 'completed',
                'payment_reference' => 'TEST_MAYAR_001',
                'customer_name' => 'Pengguna Slot 1',
            ]);

            Transaction::create([
                'slot_number' => 1,
                'nominal_paid' => 2000,
                'duration_seconds' => 1800,
                'started_at' => Carbon::now()->subHours(1),
                'ended_at' => Carbon::now()->subHours(1)->addMinutes(30),
                'status' => 'completed',
                'payment_reference' => 'TEST_MAYAR_002',
                'customer_name' => 'Pengguna Slot 1',
            ]);
        }

        // 4. Data Awal Sensor PZEM & DHT22
        if (PowerReading::count() === 0) {
            for ($i = 60; $i >= 0; $i -= 10) {
                PowerReading::create([
                    'voltage' => 220.5 + (rand(-15, 15) / 10),
                    'current' => 0.45 + (rand(-5, 5) / 100),
                    'power' => 95.0 + (rand(-10, 10)),
                    'energy' => 0.1250 + ((60 - $i) * 0.001),
                    'frequency' => 50.0,
                    'power_factor' => 0.96,
                    'recorded_at' => Carbon::now()->subMinutes($i),
                ]);

                TemperatureReading::create([
                    'temperature' => 29.5 + (rand(-5, 15) / 10),
                    'humidity' => 65.0 + (rand(-20, 20) / 10),
                    'recorded_at' => Carbon::now()->subMinutes($i),
                ]);
            }
        }
    }
}
