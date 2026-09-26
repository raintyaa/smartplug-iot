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
                'name' => 'Operator Console',
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

        // Sistem dimulai dari nol (clean state) tanpa data dummy transaksi atau sensor.
    }
}
