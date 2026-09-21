<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use App\Services\FirebaseService;

class SettingController extends Controller
{
    protected FirebaseService $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $settings = Setting::all()->keyBy('key');

        // Ambil konfigurasi realtime dari Firebase sebagai perbandingan
        $firebasePricing = $this->firebase->getPricingConfig();

        return view('settings.index', compact('settings', 'firebasePricing'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'rental_price_per_step' => 'required|numeric|min:500|max:100000',
            'rental_duration_minutes' => 'required|numeric|min:1|max:720',
            'tariff_pln_per_kwh' => 'required|numeric|min:0|max:10000',
        ]);

        $price = (int) $validated['rental_price_per_step'];
        $minutes = (int) $validated['rental_duration_minutes'];
        $seconds = $minutes * 60;
        $plnTariff = (float) $validated['tariff_pln_per_kwh'];

        // 1. Simpan ke database lokal MySQL
        Setting::set('rental_price_per_15min', (string) $price, 'Harga Sewa Dasar (Rp)');
        Setting::set('rental_duration_minutes', (string) $minutes, 'Durasi Sewa Dasar (Menit)');
        Setting::set('tariff_pln_per_kwh', (string) $plnTariff, 'Tarif Listrik PLN (Rp/kWh)');

        // 2. Sinkronkan langsung ke Firebase RTDB agar dibaca Webhook Mayar
        $synced = $this->firebase->updatePricingConfig($price, $seconds);

        if ($synced) {
            return back()->with('success', "Tarif sewa (Rp " . number_format($price, 0, ',', '.') . " = {$minutes} Menit) berhasil disimpan dan tersinkronisasi ke Firebase RTDB!");
        }

        return back()->with('warning', 'Tarif tersimpan di database lokal, namun gagal menghubungi Firebase.');
    }
}
