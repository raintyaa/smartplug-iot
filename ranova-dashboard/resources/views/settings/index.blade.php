@extends('layouts.app')

@section('title', 'Pengaturan Sistem')
@section('page_title', 'Konfigurasi Parameter Sistem')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <h2 class="text-base font-bold text-slate-100 mb-1">Parameter Operasional & Ambang Batas Keselamatan</h2>
        <p class="text-xs text-slate-400 mb-6">Perubahan di sini akan mempengaruhi kalkulasi finansial dan perlindungan termal hardware.</p>

        <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- 1. Tarif Listrik PLN -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <label for="tariff_pln_per_kwh" class="block text-xs font-semibold text-slate-200 mb-1">
                    Tarif Dasar Listrik PLN (Rp per kWh)
                </label>
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400">Rp</span>
                    <input type="number" step="0.01" name="tariff_pln_per_kwh" id="tariff_pln_per_kwh" 
                        value="{{ old('tariff_pln_per_kwh', $settings['tariff_pln_per_kwh']->value ?? '1444.70') }}" required
                        class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">Standar tarif golongan R-1/TR daya 1.300 VA adalah Rp 1.444,70 / kWh.</p>
            </div>

            <!-- 2. Harga Sewa per 15 Menit -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <label for="rental_price_per_15min" class="block text-xs font-semibold text-slate-200 mb-1">
                    Harga Sewa per 15 Menit (Rp)
                </label>
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400">Rp</span>
                    <input type="number" step="100" name="rental_price_per_15min" id="rental_price_per_15min" 
                        value="{{ old('rental_price_per_15min', $settings['rental_price_per_15min']->value ?? '1000') }}" required
                        class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">Nominal pembayaran QRIS yang dikonversikan menjadi durasi 15 menit (900 detik).</p>
            </div>

            <!-- 3. Ambang Batas Suhu Overheat -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <label for="overheat_threshold_c" class="block text-xs font-semibold text-slate-200 mb-1">
                    Batas Suhu Kritis Box (°C)
                </label>
                <div class="relative mt-1">
                    <input type="number" step="0.5" min="30" max="90" name="overheat_threshold_c" id="overheat_threshold_c" 
                        value="{{ old('overheat_threshold_c', $settings['overheat_threshold_c']->value ?? '60.0') }}" required
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                </div>
                <p class="text-[11px] text-rose-400/80 mt-1.5">Jika suhu DHT22 melampaui angka ini, firmware otomatis memutus semua relay secara darurat.</p>
            </div>

            <!-- 4. Timeout QRIS -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <label for="qris_timeout_seconds" class="block text-xs font-semibold text-slate-200 mb-1">
                    Timeout Menunggu Pembayaran QRIS (Detik)
                </label>
                <div class="relative mt-1">
                    <input type="number" step="5" min="15" max="300" name="qris_timeout_seconds" id="qris_timeout_seconds" 
                        value="{{ old('qris_timeout_seconds', $settings['qris_timeout_seconds']->value ?? '60') }}" required
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">Waktu tunggu maksimal setelah tombol ditekan sebelum slot dibatalkan otomatis.</p>
            </div>

            <div class="flex justify-end pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition shadow-lg shadow-emerald-500/20 flex items-center space-x-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Simpan Perubahan</span>
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
