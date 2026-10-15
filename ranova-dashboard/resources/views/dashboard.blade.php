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
                    <div class="my-3 text-center">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-widest text-neutral-300">Sisa Waktu Sewa</p>
                        @php
                            $activeDur = (int) ($slot['active_duration'] ?? $slot['duration_seconds'] ?? 0);
                        @endphp
                        <p class="mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-300" id="countdown-slot-{{ $i }}" aria-live="off" aria-atomic="true">
                            @if ($status === 'ACTIVE')
                                {{ $activeDur == 0 ? 'Menghitung…' : '--:--' }}
                            @elseif ($status === 'WAITING_PAYMENT')
                                SCAN QR
                            @else
                                00:00
                            @endif
                        </p>
                        <!-- Indikator Beban Listrik (Idle / Terhubung) -->
                        <div id="load-status-slot-{{ $i }}" class="mt-2 min-h-[24px]" aria-live="polite" aria-atomic="true">
                            @if ($status === 'ACTIVE')
                                @php
                                    $pzemPower = (float) ($firebaseData['sensors']['power'] ?? 0);
                                @endphp
                                @if ($pzemPower < 2.0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-amber/15 text-amber border border-amber/30 animate-pulse">
                                        <i class="fa-solid fa-triangle-exclamation mr-1.5 text-xs" aria-hidden="true"></i> Tanpa Beban (0.0 W)
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-mint/15 text-mint border border-mint/30" title="Daya terukur adalah total gabungan seluruh slot aktif pada sensor utama.">
                                        <i class="fa-solid fa-bolt mr-1.5 text-xs" aria-hidden="true"></i> Total Stasiun: {{ number_format($pzemPower, 1) }} W (Agregat)
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>

                    <!-- Details: Durasi & Dibayar -->
                    <div class="border-t border-hairline pt-3 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-300">Durasi</span>
                            <span class="font-mono text-white font-medium" id="duration-slot-{{ $i }}">
                                @if ($status === 'ACTIVE')
                                    {{ $activeDur == 0 ? 'Kontinu (Tanpa Batas)' : round($activeDur / 60) . ' Menit' }}
                                @elseif ($status === 'WAITING_PAYMENT')
                                    Menunggu
                                @else
                                    Standby
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-300">Dibayar</span>
                            <span class="font-mono text-white font-medium" id="paid-slot-{{ $i }}">
                                @if ($status === 'ACTIVE')
                                    {{ $activeDur == 0 ? 'Bypass (Rp 0)' : 'Rp ' . (isset($slot['nominal_paid']) ? number_format($slot['nominal_paid'], 0, ',', '.') : '1.000') }}
                                @elseif ($status === 'WAITING_PAYMENT')
                                    Menunggu
                                @else
                                    Rp 0
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons: Kontrol Admin (Nyalakan Manual / Matikan) -->
                    <div class="mt-4" id="slot-actions-{{ $i }}">
                        @if ($status === 'ACTIVE')
                            <form action="{{ route('dashboard.slots.stop', $i) }}" method="POST" class="js-confirm-form" data-confirm-title="Matikan Slot {{ $i }}?" data-confirm-message="Relay Slot {{ $i }} akan dimatikan. Sisa waktu sewa pelanggan ikut terhenti." data-confirm-text="Ya, Matikan" data-confirm-tone="crimson">
                                @csrf
                                <button type="submit" id="btn-action-slot-{{ $i }}"
                                    class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-4 rounded-lg bg-crimson/15 border border-crimson/40 text-crimson hover:bg-crimson/25 flex items-center justify-center space-x-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                                    <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                                    <span>Matikan Manual</span>
                                </button>
                            </form>
                        @elseif ($status === 'WAITING_PAYMENT')
                            <div class="grid grid-cols-2 gap-2">
                                <form action="{{ route('dashboard.slots.stop', $i) }}" method="POST" class="js-confirm-form" data-confirm-title="Batalkan sesi Slot {{ $i }}?" data-confirm-message="Sesi scan QR Slot {{ $i }} akan dibatalkan dan slot kembali SIAP." data-confirm-text="Ya, Batalkan" data-confirm-tone="neutral">
                                    @csrf
                                    <button type="submit" class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-3 rounded-lg bg-surface2 border border-hairline text-neutral-300 hover:bg-white/[0.04] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                                        Batal
                                    </button>
                                </form>
                                <form action="{{ route('dashboard.slots.start', $i) }}" method="POST" class="js-confirm-form" data-confirm-title="Nyalakan gratis Slot {{ $i }}?" data-confirm-message="Slot {{ $i }} akan ON terus TANPA BAYAR dan TANPA BATAS WAKTU (Rp 0) sampai dimatikan manual. Lanjut?" data-confirm-text="Ya, Nyalakan Gratis" data-confirm-tone="mint">
                                    @csrf
                                    <button type="submit" class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-3 rounded-lg bg-mint/15 border border-mint/40 text-mint hover:bg-mint/25 transition-colors flex items-center justify-center space-x-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                                        <span>Nyalakan Gratis</span>
                                    </button>
                                </form>
                            </div>
                        @else
                            <form action="{{ route('dashboard.slots.start', $i) }}" method="POST" class="js-confirm-form" data-confirm-title="Nyalakan gratis Slot {{ $i }}?" data-confirm-message="Slot {{ $i }} akan ON terus TANPA BAYAR dan TANPA BATAS WAKTU (Rp 0) sampai dimatikan manual. Lanjut?" data-confirm-text="Ya, Nyalakan Gratis" data-confirm-tone="mint">
                                @csrf
                                <button type="submit" id="btn-action-slot-{{ $i }}"
                                    class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-4 rounded-lg bg-mint/15 border border-mint/40 text-mint hover:bg-mint/25 flex items-center justify-center space-x-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                                    <span>Nyalakan Gratis (Tanpa Batas)</span>
                                </button>
                            </form>
                        @endif
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
                    {{ number_format($firebaseData['sensors']['voltage'] ?? 0, 1) }} <span class="text-xs font-normal text-neutral-300">V</span>
                </p>
            </div>
            <!-- Arus -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Arus</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-curr">
                    {{ number_format($firebaseData['sensors']['current'] ?? 0, 2) }} <span class="text-xs font-normal text-neutral-300">A</span>
                </p>
            </div>
            <!-- Daya Aktif -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Daya Aktif</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-power">
                    {{ number_format($firebaseData['sensors']['power'] ?? 0, 1) }} <span class="text-xs font-normal text-neutral-300">W</span>
                </p>
            </div>
            <!-- Energi Kumulatif -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Energi Kumulatif</p>
                <p class="font-mono text-lg text-white font-semibold mt-1">
                    <span id="sensor-energy">{{ number_format($firebaseData['sensors']['energy'] ?? 0, 4) }}</span> <span class="text-xs font-normal text-neutral-300">kWh</span>
                </p>
            </div>
            <!-- Suhu Box Internal -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Suhu Box Internal</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-temp">
                    {{ number_format($firebaseData['sensors']['temperature'] ?? 0, 1) }}°C
                </p>
                <div class="mt-1" id="temp-badge">
                    <span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-mint/10 text-mint border border-mint/25">Normal &lt; {{ round($overheatThreshold) }}°C</span>
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
    window.hasCustomDashboardLoop = true;
    // Polling Realtime Engine (setiap 2 detik sinkron dengan Firebase & Database)
    const OVERHEAT_LIMIT = {{ $overheatThreshold }};
    const CSRF_TOKEN = '{{ csrf_token() }}';
    const ESP32_OFFLINE_THRESHOLD_MS = 25000; // 25 detik tanpa update = offline (sinkron dengan diff > 25)
    const ESP32_OFFLINE_THRESHOLD_S = Math.round(ESP32_OFFLINE_THRESHOLD_MS / 1000);

    // Menyimpan Unix timestamp (seconds) dari updated_at terakhir yang diterima
    let lastSensorUpdatedAt = 0;
    // Cache status slot agar actionsEl.innerHTML HANYA di-rebuild saat status berubah (anti DOM-thrash, jaga fokus Alex)
    const lastSlotStatus = { 1: null, 2: null, 3: null };

    function fmtElapsed(totalSec) {
        const h = Math.floor(totalSec / 3600).toString().padStart(2, '0');
        const m = Math.floor((totalSec % 3600) / 60).toString().padStart(2, '0');
        const s = (totalSec % 60).toString().padStart(2, '0');
        return `${h}:${m}:${s}`;
    }

    function slotActionTemplate(i, status) {
        const focusMint = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base';
        const focusCrimson = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson focus-visible:ring-offset-2 focus-visible:ring-offset-base';
        if (status === 'ACTIVE') {
            return `
                <form action="/dashboard/slots/${i}/stop" method="POST" class="js-confirm-form" data-confirm-title="Matikan Slot ${i}?" data-confirm-message="Relay Slot ${i} akan dimatikan. Sisa waktu sewa pelanggan ikut terhenti." data-confirm-text="Ya, Matikan" data-confirm-tone="crimson">
                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                    <button type="submit" id="btn-action-slot-${i}"
                        class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-4 rounded-lg bg-crimson/15 border border-crimson/40 text-crimson hover:bg-crimson/25 flex items-center justify-center space-x-1.5 transition-colors ${focusCrimson}">
                        <i class="fa-solid fa-power-off" aria-hidden="true"></i>
                        <span>Matikan Manual</span>
                    </button>
                </form>
            `;
        }
        if (status === 'WAITING_PAYMENT') {
            return `
                <div class="grid grid-cols-2 gap-2">
                    <form action="/dashboard/slots/${i}/stop" method="POST" class="js-confirm-form" data-confirm-title="Batalkan sesi Slot ${i}?" data-confirm-message="Sesi scan QR Slot ${i} akan dibatalkan dan slot kembali SIAP." data-confirm-text="Ya, Batalkan" data-confirm-tone="neutral">
                        <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                        <button type="submit" class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-3 rounded-lg bg-surface2 border border-hairline text-neutral-300 hover:bg-white/[0.04] transition-colors ${focusMint}">
                            Batal
                        </button>
                    </form>
                    <form action="/dashboard/slots/${i}/start" method="POST" class="js-confirm-form" data-confirm-title="Nyalakan gratis Slot ${i}?" data-confirm-message="Slot ${i} akan ON terus TANPA BAYAR dan TANPA BATAS WAKTU (Rp 0) sampai dimatikan manual. Lanjut?" data-confirm-text="Ya, Nyalakan Gratis" data-confirm-tone="mint">
                        <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                        <button type="submit" class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-3 rounded-lg bg-mint/15 border border-mint/40 text-mint hover:bg-mint/25 transition-colors flex items-center justify-center space-x-1 ${focusMint}">
                            <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                            <span>Nyalakan Gratis</span>
                        </button>
                    </form>
                </div>
            `;
        }
        return `
            <form action="/dashboard/slots/${i}/start" method="POST" class="js-confirm-form" data-confirm-title="Nyalakan gratis Slot ${i}?" data-confirm-message="Slot ${i} akan ON terus TANPA BAYAR dan TANPA BATAS WAKTU (Rp 0) sampai dimatikan manual. Lanjut?" data-confirm-text="Ya, Nyalakan Gratis" data-confirm-tone="mint">
                <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                <button type="submit" id="btn-action-slot-${i}"
                    class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-4 rounded-lg bg-mint/15 border border-mint/40 text-mint hover:bg-mint/25 flex items-center justify-center space-x-1.5 transition-colors ${focusMint}">
                    <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                    <span>Nyalakan Gratis (Tanpa Batas)</span>
                </button>
            </form>
        `;
    }

    function renderSlotActions(i, status) {
        if (lastSlotStatus[i] === status) return; // status sama -> biarkan DOM utuh, fokus tidak hilang
        lastSlotStatus[i] = status;
        const actionsEl = document.getElementById('slot-actions-' + i);
        if (actionsEl) actionsEl.innerHTML = slotActionTemplate(i, status);
    }

    function getEsp32Badge() {
        return document.getElementById('esp32-badge');
    }

    function setEsp32Online() {
        const badge = getEsp32Badge();
        if (badge) {
            badge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-mint/10 text-mint';
            badge.setAttribute('title', 'ESP32 online — telemetri realtime');
            badge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">ESP32</span><span class="sr-only">ESP32 online</span>';
        }
    }

    function setEsp32Offline() {
        const badge = getEsp32Badge();
        if (badge) {
            badge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-400';
            badge.setAttribute('title', 'ESP32 offline — data sensor basi');
            badge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">ESP32 Offline</span><span class="sr-only">ESP32 offline</span>';
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
                if (syncEl) syncEl.innerText = 'Sinkron: ' + timeStr;

                // 1. Update 3 Slot Stop Kontak
                let activeSlotsCount = 0;
                for (let k = 1; k <= 3; k++) {
                    const s = data.slots['slot' + k] || {};
                    if ((s.status || '').toUpperCase() === 'ACTIVE') activeSlotsCount++;
                }

                const currentPzemPower = parseFloat(data.sensors?.power || 0);
                const currentPzemCurr = parseFloat(data.sensors?.current || 0);

                for (let i = 1; i <= 3; i++) {
                    const slot = data.slots['slot' + i] || { status: 'STANDBY', active_duration: 0, started_at: 0 };
                    const status = (slot.status || 'STANDBY').toUpperCase();
                    const badge = document.getElementById('badge-slot-' + i);
                    const countdown = document.getElementById('countdown-slot-' + i);
                    const duration = document.getElementById('duration-slot-' + i);
                    const paid = document.getElementById('paid-slot-' + i);
                    const card = document.getElementById('card-slot-' + i);
                    const loadStatusEl = document.getElementById('load-status-slot-' + i);
                    const actionsEl = document.getElementById('slot-actions-' + i);

                    if (!card) continue;

                    // Styling badge sesuai status
                    if (status === 'ACTIVE') {
                        badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-mint/10 border border-mint/25 text-mint uppercase tracking-wide';
                        badge.innerText = 'AKTIF';
                        card.className = 'rounded-xl bg-surface2 border border-mint/40 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';

                        // Tombol Aksi Admin: hanya render ulang jika status berubah (anti DOM-thrash)
                        renderSlotActions(i, status);

                        // Indikator Status Beban Listrik — jujur: 1 sensor PZEM total busbar, bukan per-slot
                        if (loadStatusEl) {
                            if (currentPzemPower < 2.0 && currentPzemCurr < 0.03) {
                                loadStatusEl.innerHTML = `
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-amber/15 text-amber border border-amber/30 animate-pulse" title="Arus < 0,03 A dan daya < 2,0 W. Charger belum dicolok atau sudah dicabut.">
                                        <i class="fa-solid fa-triangle-exclamation mr-1.5 text-xs" aria-hidden="true"></i> Tanpa Beban (0.0 W)
                                    </span>
                                `;
                            } else {
                                if (activeSlotsCount === 1) {
                                    loadStatusEl.innerHTML = `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-mint/15 text-mint border border-mint/30" title="Hanya 1 slot aktif, angka ini milik slot tersebut (sensor utama).">
                                            <i class="fa-solid fa-bolt mr-1.5 text-xs" aria-hidden="true"></i> Beban Slot: ${currentPzemPower.toFixed(1)} W (Tunggal)
                                        </span>
                                    `;
                                } else {
                                    loadStatusEl.innerHTML = `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-mint/15 text-mint border border-mint/30" title="Daya terukur adalah total gabungan seluruh slot aktif pada sensor utama.">
                                            <i class="fa-solid fa-bolt mr-1.5 text-xs" aria-hidden="true"></i> Total Stasiun: ${currentPzemPower.toFixed(1)} W (Agregat ${activeSlotsCount} Slot)
                                        </span>
                                    `;
                                }
                            }
                        }

                        // Hitung Countdown atau Mode Kontinu
                        const startedAt = parseInt(slot.started_at) || 0;
                        const durationSec = parseInt(slot.active_duration) || 0;
                        const nowSec = Math.floor(Date.now() / 1000);
                        const elapsed = nowSec - startedAt;

                        if (durationSec === 0) {
                            // Mode Manual Kontinu (Tanpa Batas Durasi) + elapsed timer + hazard > 1 jam
                            const safeStartedAt = startedAt > 0 ? startedAt : nowSec;
                            const manualElapsed = Math.max(0, nowSec - safeStartedAt);
                            countdown.innerText = 'Menyala: ' + fmtElapsed(manualElapsed) + ' (Manual)';
                            countdown.className = 'mt-1 font-mono text-2xl font-bold tracking-tight text-amber';
                            duration.innerText = 'Kontinu (Tanpa Batas)';
                            paid.innerText = 'Gratis (Rp 0)';
                            if (loadStatusEl) {
                                if (manualElapsed > 3600) {
                                    loadStatusEl.innerHTML += `
                                        <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-amber/15 text-amber border border-amber/40 animate-pulse" role="alert" title="Slot manual menyala lebih dari 1 jam. Periksa beban dan matikan bila tidak dipakai.">
                                            <i class="fa-solid fa-triangle-exclamation mr-1.5 text-xs" aria-hidden="true"></i> Menyala &gt; 1 Jam (Periksa Beban)
                                        </span>
                                    `;
                                }
                            }
                        } else {
                            const remaining = Math.max(0, durationSec - elapsed);
                            const m = Math.floor(remaining / 60).toString().padStart(2, '0');
                            const s = (remaining % 60).toString().padStart(2, '0');
                            countdown.innerText = `${m}:${s}`;
                            countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-mint';
                            duration.innerText = Math.round(durationSec / 60) + ' Menit';
                            paid.innerText = 'Rp ' + (slot.nominal_paid ? Number(slot.nominal_paid).toLocaleString('id-ID') : '1.000');
                        }
                    } else if (status === 'WAITING_PAYMENT') {
                        if (loadStatusEl) loadStatusEl.innerHTML = '';
                        badge.className = 'pulse-amber font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-amber/10 border border-amber/25 text-amber uppercase tracking-wide';
                        badge.innerText = 'MENUNGGU BAYAR';
                        card.className = 'rounded-xl bg-surface border border-amber/30 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        countdown.innerText = 'SCAN QR';
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-amber';
                        duration.innerText = 'Menunggu';
                        paid.innerText = 'Menunggu';

                        // Tombol Batal & Nyalakan Gratis: hanya render ulang jika status berubah
                        renderSlotActions(i, status);
                    } else {
                        if (loadStatusEl) loadStatusEl.innerHTML = '';
                        badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-surface2 border border-hairline text-neutral-300 uppercase tracking-wide';
                        badge.innerText = 'SIAP';
                        card.className = 'rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        countdown.innerText = '00:00';
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-300';
                        duration.innerText = 'Standby';
                        paid.innerText = 'Rp 0';

                        // Tombol Nyalakan Gratis: hanya render ulang jika status berubah
                        renderSlotActions(i, status);
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

                    if (lastSensorUpdatedAt === 0 || secondsSinceUpdate > ESP32_OFFLINE_THRESHOLD_S) {
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
                                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-mint/10 text-mint border border-mint/25">Normal &lt; ' + Math.round(OVERHEAT_LIMIT) + '°C</span>';
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
