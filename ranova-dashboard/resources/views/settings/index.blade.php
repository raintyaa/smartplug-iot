@extends('layouts.app')

@section('title', 'Pengaturan Tarif & Sistem')
@section('page_title', 'Konfigurasi Tarif Sewa & Sistem')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Card Utama Pengaturan -->
    <div class="rounded-xl bg-surface border border-hairline p-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6 pb-4 border-b border-hairline">
            <div>
                <h2 class="text-base font-semibold text-white tracking-tight">Tarif Sewa Stop Kontak</h2>
                <p class="text-xs text-neutral-300 max-w-md mt-1 leading-relaxed">
                    Konfigurasi ini langsung mengatur durasi aktif relay saat pembayaran QRIS terkonfirmasi.
                </p>
            </div>
            <span class="inline-flex items-center px-2.5 py-1 rounded bg-mint/10 text-mint border border-mint/25 text-xs font-mono font-medium">
                <i class="fa-solid fa-cloud-arrow-up mr-1.5 text-xs"></i> Firebase Sync
            </span>
        </div>

        <form action="{{ route('settings.update') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Form Grid: 2 Kolom -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- 1. Nominal Dasar Pembayaran -->
                <div>
                    <label for="rental_price_per_step" class="block text-xs font-medium text-neutral-200 mb-1.5">
                        Nominal Pembayaran Dasar (Rp)
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-mono text-neutral-400">Rp</span>
                        <input type="number" step="500" min="500" name="rental_price_per_step" id="rental_price_per_step" 
                            value="{{ old('rental_price_per_step', $firebasePricing['base_price'] ?? 1000) }}" required
                            oninput="updatePreview()"
                            class="w-full pl-10 pr-4 py-2.5 bg-surface2 border border-hairline rounded-lg text-sm font-mono text-white focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                    </div>
                    <p class="text-xs text-neutral-400 mt-1.5">Kelipatan nominal pembayaran pelanggan.</p>
                </div>

                <!-- 2. Durasi Waktu Aktif -->
                <div>
                    <label for="rental_duration_minutes" class="block text-xs font-medium text-neutral-200 mb-1.5">
                        Durasi Waktu yang Didapatkan (Menit)
                    </label>
                    <div class="relative">
                        <input type="number" step="1" min="1" max="720" name="rental_duration_minutes" id="rental_duration_minutes" 
                            value="{{ old('rental_duration_minutes', round(($firebasePricing['base_duration_seconds'] ?? 900) / 60)) }}" required
                            oninput="updatePreview()"
                            class="w-full pl-3.5 pr-16 py-2.5 bg-surface2 border border-hairline rounded-lg text-sm font-mono text-white focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-xs font-mono text-neutral-400">Menit</span>
                    </div>
                    <p class="text-xs text-neutral-400 mt-1.5">Durasi relay aktif menyala per kelipatan tarif.</p>
                </div>
            </div>

            <!-- Preview Kalkulasi Otomatis (Clean Stat List, 0 Nested Cards) -->
            <div class="pt-5 border-t border-hairline">
                <p class="text-xs font-semibold text-neutral-200 mb-3 flex items-center space-x-2">
                    <i class="fa-solid fa-calculator text-mint text-xs"></i>
                    <span>Simulasi Alur Transaksi Pelanggan</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center divide-y sm:divide-y-0 sm:divide-x divide-hairline">
                    <div class="px-3 py-2">
                        <span class="text-xs font-mono text-neutral-400 block">Bayar 1x</span>
                        <span class="font-mono text-sm font-bold text-white mt-1 block" id="prev-pay-1">Rp 1.000</span>
                        <span class="font-mono text-xs font-semibold text-mint mt-1 block" id="prev-dur-1">Aktif 15 Menit</span>
                    </div>
                    <div class="px-3 py-2">
                        <span class="text-xs font-mono text-neutral-400 block">Bayar 2x</span>
                        <span class="font-mono text-sm font-bold text-white mt-1 block" id="prev-pay-2">Rp 2.000</span>
                        <span class="font-mono text-xs font-semibold text-mint mt-1 block" id="prev-dur-2">Aktif 30 Menit</span>
                    </div>
                    <div class="px-3 py-2">
                        <span class="text-xs font-mono text-neutral-400 block">Bayar 5x</span>
                        <span class="font-mono text-sm font-bold text-white mt-1 block" id="prev-pay-3">Rp 5.000</span>
                        <span class="font-mono text-xs font-semibold text-mint mt-1 block" id="prev-dur-3">Aktif 75 Menit</span>
                    </div>
                </div>
            </div>

            <!-- 3. Tarif Dasar Listrik PLN -->
            <div class="pt-5 border-t border-hairline">
                <label for="tariff_pln_per_kwh" class="block text-xs font-medium text-neutral-200 mb-1.5">
                    Tarif Dasar Listrik PLN untuk Laporan Laba Rugi (Rp / kWh)
                </label>
                <div class="relative max-w-sm">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-xs font-mono text-neutral-400">Rp</span>
                    <input type="number" step="0.01" name="tariff_pln_per_kwh" id="tariff_pln_per_kwh" 
                        value="{{ old('tariff_pln_per_kwh', $settings['tariff_pln_per_kwh']->value ?? '1444.70') }}" required
                        class="w-full pl-10 pr-4 py-2.5 bg-surface2 border border-hairline rounded-lg text-sm font-mono text-white focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                </div>
                <p class="text-xs text-neutral-400 max-w-md mt-1.5 leading-relaxed">
                    Dasar kalkulasi biaya listrik pada laporan finansial menggunakan sensor PZEM-004T.
                </p>
            </div>

            <!-- Tombol Simpan -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-5 border-t border-hairline">
                <p class="text-xs font-mono text-neutral-300">
                    Tersinkronisasi otomatis ke Firebase RTDB (<span class="text-neutral-200">/config/pricing</span>)
                </p>
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-mint hover:bg-mint/90 text-neutral-950 font-mono font-semibold text-xs transition flex items-center space-x-2">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Simpan &amp; Sinkronkan ke Sistem</span>
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
        document.getElementById('prev-dur-1').innerText = 'Aktif ' + baseMin + ' Menit';

        document.getElementById('prev-pay-2').innerText = 'Rp ' + (basePrice * 2).toLocaleString('id-ID');
        document.getElementById('prev-dur-2').innerText = 'Aktif ' + (baseMin * 2) + ' Menit';

        document.getElementById('prev-pay-3').innerText = 'Rp ' + (basePrice * 5).toLocaleString('id-ID');
        document.getElementById('prev-dur-3').innerText = 'Aktif ' + (baseMin * 5) + ' Menit';
    }

    document.addEventListener('DOMContentLoaded', updatePreview);
</script>
@endpush
