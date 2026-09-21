@extends('layouts.app')

@section('title', 'Pengaturan Tarif & Sistem')
@section('page_title', 'Konfigurasi Tarif Sewa & Sistem')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Card Pengaturan Tarif Dinamis -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-base font-bold text-slate-100">Tarif Sewa Stop Kontak (Terkoneksi Realtime)</h2>
                <p class="text-xs text-slate-400 mt-0.5">Pengaturan di bawah ini <b>langsung mempengaruhi durasi aktif stop kontak</b> saat pembayaran QRIS Mayar masuk!</p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <i class="fa-solid fa-cloud-arrow-up mr-1.5 text-xs"></i> Firebase Sync
            </span>
        </div>

        <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- 1. Nominal Dasar Pembayaran -->
                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                    <label for="rental_price_per_step" class="block text-xs font-semibold text-slate-200 mb-1">
                        Nominal Pembayaran Dasar (Rp)
                    </label>
                    <div class="relative mt-1">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400">Rp</span>
                        <input type="number" step="500" min="500" name="rental_price_per_step" id="rental_price_per_step" 
                            value="{{ old('rental_price_per_step', $firebasePricing['base_price'] ?? 1000) }}" required
                            oninput="updatePreview()"
                            class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1.5">Kelipatan uang yang dibayarkan pelanggan.</p>
                </div>

                <!-- 2. Durasi Waktu Aktif -->
                <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                    <label for="rental_duration_minutes" class="block text-xs font-semibold text-slate-200 mb-1">
                        Durasi Waktu yang Didapatkan (Menit)
                    </label>
                    <div class="relative mt-1">
                        <input type="number" step="1" min="1" max="720" name="rental_duration_minutes" id="rental_duration_minutes" 
                            value="{{ old('rental_duration_minutes', round(($firebasePricing['base_duration_seconds'] ?? 900) / 60)) }}" required
                            oninput="updatePreview()"
                            class="w-full pl-3 pr-14 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                        <span class="absolute inset-y-0 right-0 pr-3 flex items-center text-xs text-slate-400 font-medium">Menit</span>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-1.5">Berapa menit relay stop kontak akan aktif menyala.</p>
                </div>
            </div>

            <!-- Preview Kalkulasi Otomatis -->
            <div class="p-4 rounded-xl bg-emerald-500/5 border border-emerald-500/20">
                <p class="text-xs font-bold text-emerald-400 mb-2 flex items-center space-x-1.5">
                    <i class="fa-solid fa-calculator"></i>
                    <span>Simulasi Alur Transaksi Pelanggan:</span>
                </p>
                <div class="grid grid-cols-3 gap-3 text-xs text-slate-300">
                    <div class="p-2.5 rounded-lg bg-slate-950/80 border border-slate-800">
                        <span class="text-slate-400 block text-[11px]">Jika Bayar:</span>
                        <span class="font-bold text-slate-100" id="prev-pay-1">Rp 1.000</span>
                        <span class="text-emerald-400 block text-[11px] font-semibold mt-1" id="prev-dur-1">➔ Aktif 15 Menit</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-950/80 border border-slate-800">
                        <span class="text-slate-400 block text-[11px]">Jika Bayar:</span>
                        <span class="font-bold text-slate-100" id="prev-pay-2">Rp 2.000</span>
                        <span class="text-emerald-400 block text-[11px] font-semibold mt-1" id="prev-dur-2">➔ Aktif 30 Menit</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-slate-950/80 border border-slate-800">
                        <span class="text-slate-400 block text-[11px]">Jika Bayar:</span>
                        <span class="font-bold text-slate-100" id="prev-pay-3">Rp 5.000</span>
                        <span class="text-emerald-400 block text-[11px] font-semibold mt-1" id="prev-dur-3">➔ Aktif 75 Menit</span>
                    </div>
                </div>
            </div>

            <!-- 3. Tarif Dasar Listrik PLN -->
            <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800">
                <label for="tariff_pln_per_kwh" class="block text-xs font-semibold text-slate-200 mb-1">
                    Tarif Dasar Listrik PLN untuk Laporan Laba Rugi (Rp / kWh)
                </label>
                <div class="relative mt-1">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-xs text-slate-400">Rp</span>
                    <input type="number" step="0.01" name="tariff_pln_per_kwh" id="tariff_pln_per_kwh" 
                        value="{{ old('tariff_pln_per_kwh', $settings['tariff_pln_per_kwh']->value ?? '1444.70') }}" required
                        class="w-full pl-9 pr-4 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">Digunakan oleh halaman Laporan Finansial untuk menghitung biaya riil konsumsi listrik dari sensor PZEM-004T.</p>
            </div>

            <!-- Tombol Simpan -->
            <div class="flex items-center justify-between pt-2">
                <p class="text-xs text-slate-500">Nilai akan langsung diperbarui ke Firebase RTDB (`/config/pricing`).</p>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition shadow-lg shadow-emerald-500/20 flex items-center space-x-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Simpan & Sinkronkan ke Sistem</span>
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function updatePreview() {
        const basePrice = parseInt(document.getElementById('rental_price_per_step').value) || 1000;
        const baseMin = parseInt(document.getElementById('rental_duration_minutes').value) || 15;

        // Simulasi 1x, 2x, 5x
        document.getElementById('prev-pay-1').innerText = 'Rp ' + basePrice.toLocaleString('id-ID');
        document.getElementById('prev-dur-1').innerText = '➔ Aktif ' + baseMin + ' Menit';

        document.getElementById('prev-pay-2').innerText = 'Rp ' + (basePrice * 2).toLocaleString('id-ID');
        document.getElementById('prev-dur-2').innerText = '➔ Aktif ' + (baseMin * 2) + ' Menit';

        document.getElementById('prev-pay-3').innerText = 'Rp ' + (basePrice * 5).toLocaleString('id-ID');
        document.getElementById('prev-dur-3').innerText = '➔ Aktif ' + (baseMin * 5) + ' Menit';
    }

    document.addEventListener('DOMContentLoaded', updatePreview);
</script>
@endpush
