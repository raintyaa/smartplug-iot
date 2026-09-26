@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'DASHBOARD')

@section('content')
<div class="space-y-6">

    <!-- 1. Ringkasan Finansial Hari Ini (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Pendapatan QRIS -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Pendapatan QRIS (Hari Ini)</p>
                <i class="fa-solid fa-qrcode text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-white">
                Rp {{ number_format($todayRevenue, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300">Total: <span class="font-mono text-white">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</span></p>
        </div>

        <!-- Total Konsumsi Energi -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Konsumsi Energi Total</p>
                <i class="fa-solid fa-bolt text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-white" id="stat-energy">
                {{ number_format($totalKwh, 3, ',', '.') }} <span class="text-sm font-normal text-neutral-300">kWh</span>
            </p>
            <div class="mt-2 flex items-center gap-2">
                <span class="inline-flex items-center gap-1 rounded-md border border-mint/20 bg-mint/10 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-mint">
                    PZEM-004T
                </span>
                <span class="text-xs text-neutral-300">Stasiun Utama</span>
            </div>
        </div>

        <!-- Biaya Listrik PLN -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Estimasi Biaya PLN</p>
                <i class="fa-solid fa-receipt text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-white" id="stat-pln-cost">
                Rp {{ number_format($totalPlnCost, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300">Tarif: <span class="font-mono text-white">Rp 1.444,70</span> / kWh</p>
        </div>

        <!-- Margin Bersih -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Estimasi Laba Bersih</p>
                <i class="fa-solid fa-wallet text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-mint">
                Rp {{ number_format($netProfit, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300">Pendapatan dikurangi Biaya Listrik</p>
        </div>
    </div>

    <!-- 2. Status Stop Kontak (Clean Grid - 3 Relay Cards) -->
    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-white tracking-tight">Status Stop Kontak</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @for ($i = 1; $i <= 3; $i++)
                @php
                    $slotKey = 'slot' . $i;
                    $slot = $firebaseData[$slotKey] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0];
                    $status = strtoupper($slot['status'] ?? 'STANDBY');
                @endphp
                <div class="rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300" id="card-slot-{{ $i }}">
                    <!-- Header Slot -->
                    <div class="flex items-center justify-between">
                        <p class="font-mono text-sm font-semibold text-white tracking-wide">SLOT 0{{ $i }}</p>
                        <span class="font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-surface2 border border-hairline text-neutral-300 uppercase tracking-wide" id="badge-slot-{{ $i }}">
                            {{ $status === 'ACTIVE' ? 'AKTIF' : ($status === 'WAITING_PAYMENT' ? 'MENUNGGU BAYAR' : 'SIAP') }}
                        </span>
                    </div>

                    <!-- Center Countdown Display -->
                    <div class="my-4 text-center">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-widest text-neutral-300">Sisa Waktu Sewa</p>
                        <p class="mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-300" id="countdown-slot-{{ $i }}">
                            {{ $status === 'ACTIVE' ? '14:59' : ($status === 'WAITING_PAYMENT' ? 'SCAN QR' : '00:00') }}
                        </p>
                    </div>

                    <!-- Details: Durasi & Dibayar -->
                    <div class="border-t border-hairline pt-3 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-300">Durasi</span>
                            <span class="font-mono text-white font-medium" id="duration-slot-{{ $i }}">
                                {{ $status === 'ACTIVE' ? round(($slot['active_duration'] ?? 0) / 60) . ' Menit' : ($status === 'WAITING_PAYMENT' ? 'Menunggu' : 'Standby') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-300">Dibayar</span>
                            <span class="font-mono text-white font-medium" id="paid-slot-{{ $i }}">
                                {{ $status === 'ACTIVE' ? 'Rp ' . (isset($slot['nominal_paid']) ? number_format($slot['nominal_paid'], 0, ',', '.') : '1.000') : ($status === 'WAITING_PAYMENT' ? 'Menunggu' : 'Rp 0') }}
                            </span>
                        </div>
                    </div>

                    <!-- Action Button: Matikan Manual -->
                    <div class="mt-4">
                        <form action="{{ route('dashboard.slots.stop', $i) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mematikan Slot {{ $i }}?')">
                            @csrf
                            <button type="submit" id="btn-stop-slot-{{ $i }}" {{ $status === 'STANDBY' ? 'disabled' : '' }}
                                class="w-full text-xs font-semibold py-2 rounded-lg transition-colors {{ $status === 'ACTIVE' ? 'bg-crimson/10 border border-crimson/30 text-crimson hover:bg-crimson/20' : ($status === 'WAITING_PAYMENT' ? 'bg-surface2 border border-hairline text-neutral-300 hover:bg-white/[0.04]' : 'bg-surface2/60 text-neutral-300/80 border border-hairline/80 cursor-not-allowed') }}">
                                Matikan Manual
                            </button>
                        </form>
                    </div>
                </div>
            @endfor
        </div>
    </section>

    <!-- 3. Telemetri Sensor Strip (PZEM-004T & DHT22) -->
    <section class="rounded-xl bg-surface border border-hairline p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-semibold text-white tracking-tight">Telemetri Sensor</h2>
            <span class="font-mono text-xs text-neutral-300">PZEM-004T · DHT22</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-6 divide-y md:divide-y-0 md:divide-x divide-hairline">
            <!-- Tegangan -->
            <div class="px-4 py-2 md:py-0 first:pl-0">
                <p class="text-xs text-neutral-300 font-medium">Tegangan</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-volt">
                    {{ number_format($firebaseData['sensors']['voltage'] ?? 0, 1) }} <span class="text-xs font-normal text-neutral-400">V</span>
                </p>
            </div>
            <!-- Arus -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Arus</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-curr">
                    {{ number_format($firebaseData['sensors']['current'] ?? 0, 2) }} <span class="text-xs font-normal text-neutral-400">A</span>
                </p>
            </div>
            <!-- Daya Aktif -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Daya Aktif</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-power">
                    {{ number_format($firebaseData['sensors']['power'] ?? 0, 1) }} <span class="text-xs font-normal text-neutral-400">W</span>
                </p>
            </div>
            <!-- Energi Kumulatif -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Energi Kumulatif</p>
                <p class="font-mono text-lg text-white font-semibold mt-1">
                    <span id="sensor-energy">{{ number_format($firebaseData['sensors']['energy'] ?? 0, 4) }}</span> <span class="text-xs font-normal text-neutral-400">kWh</span>
                </p>
            </div>
            <!-- Suhu Box Internal -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Suhu Box Internal</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-temp">
                    {{ number_format($firebaseData['sensors']['temperature'] ?? 0, 1) }}°C
                </p>
                <div class="mt-1" id="temp-badge">
                    <span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-mint/10 text-mint border border-mint/25">Normal &lt; {{ $overheatThreshold }}°C</span>
                </div>
            </div>
            <!-- Kelembapan -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Kelembapan</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-hum">
                    {{ round($firebaseData['sensors']['humidity'] ?? 0) }}%
                </p>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
    // Polling Realtime Engine (setiap 2 detik sinkron dengan Firebase & Database)
    const OVERHEAT_LIMIT = {{ $overheatThreshold }};
    const ESP32_OFFLINE_THRESHOLD_MS = 10000; // 10 detik tanpa update = offline

    // Menyimpan Unix timestamp (seconds) dari updated_at terakhir yang diterima
    let lastSensorUpdatedAt = 0;

    function getEsp32Badge() {
        return document.getElementById('esp32-badge');
    }

    function setEsp32Online() {
        const badge = getEsp32Badge();
        if (badge) {
            badge.className = 'font-mono text-xs font-medium px-3 py-1.5 rounded-lg bg-mint/10 border border-mint/25 text-mint';
            badge.innerText = 'ESP32';
        }
    }

    function setEsp32Offline() {
        const badge = getEsp32Badge();
        if (badge) {
            badge.className = 'font-mono text-xs font-medium px-3 py-1.5 rounded-lg bg-surface2 border border-hairline text-neutral-300';
            badge.innerText = 'ESP32';
        }
        // Nol-kan semua nilai sensor
        if (document.getElementById('sensor-temp')) {
            document.getElementById('sensor-temp').innerText = '0.0°C';
            document.getElementById('sensor-hum').innerText = '0%';
            document.getElementById('sensor-volt').innerHTML = '0.0 <span class="text-xs text-neutral-400 font-normal">V</span>';
            document.getElementById('sensor-curr').innerHTML = '0.00 <span class="text-xs text-neutral-400 font-normal">A</span>';
            document.getElementById('sensor-power').innerHTML = '0.0 <span class="text-xs text-neutral-400 font-normal">W</span>';
            document.getElementById('sensor-energy').innerText = '0.0000';
            const tempBadge = document.getElementById('temp-badge');
            if (tempBadge) {
                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-medium px-1.5 py-0.5 rounded bg-surface2 text-neutral-300 border border-hairline">Offline</span>';
            }
        }
    }

    function updateDashboard() {
        fetch('{{ route('dashboard.realtime') }}')
            .then(res => res.json())
            .then(data => {
                const now = new Date();
                const timeStr = now.toTimeString().split(' ')[0];
                const syncEl = document.getElementById('last-sync-time');
                if (syncEl) syncEl.innerText = timeStr;

                // 1. Update 3 Slot Stop Kontak
                for (let i = 1; i <= 3; i++) {
                    const slot = data.slots['slot' + i] || { status: 'STANDBY', active_duration: 0, started_at: 0 };
                    const status = (slot.status || 'STANDBY').toUpperCase();
                    const badge = document.getElementById('badge-slot-' + i);
                    const countdown = document.getElementById('countdown-slot-' + i);
                    const duration = document.getElementById('duration-slot-' + i);
                    const paid = document.getElementById('paid-slot-' + i);
                    const card = document.getElementById('card-slot-' + i);
                    const btnStop = document.getElementById('btn-stop-slot-' + i);

                    if (!card) continue;

                    // Styling badge sesuai status
                    if (status === 'ACTIVE') {
                        badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-mint/10 border border-mint/25 text-mint uppercase tracking-wide';
                        badge.innerText = 'AKTIF';
                        card.className = 'rounded-xl bg-surface2 border border-mint/40 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        btnStop.disabled = false;
                        btnStop.className = 'w-full text-xs font-semibold py-2 rounded-lg bg-crimson/10 border border-crimson/30 text-crimson hover:bg-crimson/20 transition-colors';

                        // Hitung Countdown
                        const startedAt = parseInt(slot.started_at) || 0;
                        const durationSec = parseInt(slot.active_duration) || 0;
                        const nowSec = Math.floor(Date.now() / 1000);
                        const elapsed = nowSec - startedAt;
                        const remaining = Math.max(0, durationSec - elapsed);

                        const m = Math.floor(remaining / 60).toString().padStart(2, '0');
                        const s = (remaining % 60).toString().padStart(2, '0');
                        countdown.innerText = `${m}:${s}`;
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-mint';

                        duration.innerText = Math.round(durationSec / 60) + ' Menit';
                        paid.innerText = 'Rp ' + (slot.nominal_paid ? Number(slot.nominal_paid).toLocaleString('id-ID') : '1.000');
                    } else if (status === 'WAITING_PAYMENT') {
                        badge.className = 'pulse-amber font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-amber/10 border border-amber/25 text-amber uppercase tracking-wide';
                        badge.innerText = 'MENUNGGU BAYAR';
                        card.className = 'rounded-xl bg-surface border border-amber/30 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        btnStop.disabled = false;
                        btnStop.className = 'w-full text-xs font-semibold py-2 rounded-lg bg-surface2 border border-hairline text-neutral-200 hover:bg-white/[0.04] transition-colors';
                        countdown.innerText = 'SCAN QR';
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-amber';
                        duration.innerText = 'Menunggu';
                        paid.innerText = 'Menunggu';
                    } else {
                        badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-surface2 border border-hairline text-neutral-300 uppercase tracking-wide';
                        badge.innerText = 'SIAP';
                        card.className = 'rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        btnStop.disabled = true;
                        btnStop.className = 'w-full text-xs font-semibold py-2 rounded-lg bg-surface2/60 text-neutral-300/80 border border-hairline/80 cursor-not-allowed';
                        countdown.innerText = '00:00';
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-300';
                        duration.innerText = 'Standby';
                        paid.innerText = 'Rp 0';
                    }
                }

                // 2. Update Sensor Fisik (dengan Deteksi Offline ESP32)
                if (data.sensors) {
                    const incomingUpdatedAt = parseInt(data.sensors.updated_at) || 0;

                    if (incomingUpdatedAt > lastSensorUpdatedAt) {
                        lastSensorUpdatedAt = incomingUpdatedAt;
                    }

                    const nowUnix = Math.floor(Date.now() / 1000);
                    const secondsSinceUpdate = (lastSensorUpdatedAt > 0) ? (nowUnix - lastSensorUpdatedAt) : 9999;

                    if (lastSensorUpdatedAt === 0 || secondsSinceUpdate > 10) {
                        setEsp32Offline();
                    } else {
                        setEsp32Online();

                        if (document.getElementById('sensor-temp')) {
                            const temp = parseFloat(data.sensors.temperature || 0);
                            const hum = parseFloat(data.sensors.humidity || 0);

                            document.getElementById('sensor-temp').innerText = temp.toFixed(1) + '°C';
                            document.getElementById('sensor-hum').innerText = Math.round(hum) + '%';
                            document.getElementById('sensor-volt').innerHTML = parseFloat(data.sensors.voltage || 0).toFixed(1) + ' <span class="text-xs text-neutral-400 font-normal">V</span>';
                            document.getElementById('sensor-curr').innerHTML = parseFloat(data.sensors.current || 0).toFixed(2) + ' <span class="text-xs text-neutral-400 font-normal">A</span>';
                            document.getElementById('sensor-power').innerHTML = parseFloat(data.sensors.power || 0).toFixed(1) + ' <span class="text-xs text-neutral-400 font-normal">W</span>';
                            document.getElementById('sensor-energy').innerText = parseFloat(data.sensors.energy || 0).toFixed(4);

                            const tempBadge = document.getElementById('temp-badge');
                            if (temp >= OVERHEAT_LIMIT && OVERHEAT_LIMIT > 0) {
                                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-crimson/20 text-crimson border border-crimson/30">OVERHEAT!</span>';
                            } else if (temp > 0) {
                                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-mint/10 text-mint border border-mint/25">Normal &lt; ' + OVERHEAT_LIMIT + '°C</span>';
                            } else {
                                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-surface2 text-neutral-300 border border-hairline">Standby</span>';
                            }
                        }
                    }
                }
            })
            .catch(err => {
                console.warn('Realtime sync pause:', err);
                const syncEl = document.getElementById('last-sync-time');
                if (syncEl) syncEl.innerText = 'Koneksi terputus...';
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateDashboard();
        setInterval(updateDashboard, 2000);
    });
</script>
@endpush
