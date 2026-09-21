<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->keyBy('key');
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'tariff_pln_per_kwh' => 'required|numeric|min:0',
            'rental_price_per_15min' => 'required|numeric|min:0',
            'overheat_threshold_c' => 'required|numeric|min:30|max:100',
            'qris_timeout_seconds' => 'required|numeric|min:10|max:600',
        ]);

        foreach ($validated as $key => $val) {
            Setting::where('key', $key)->update(['value' => $val]);
        }

        return back()->with('success', 'Pengaturan sistem berhasil disimpan.');
    }
}
