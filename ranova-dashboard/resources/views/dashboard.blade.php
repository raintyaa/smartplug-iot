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
                    $isOnline = $isEsp32Online ?? false;
                    $slotKey = 'slot' . $i;
                    $slot = $firebaseData[$slotKey] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0];
                    $rawStatus = strtoupper($slot['status'] ?? 'STANDBY');
                    $status = $isOnline ? $rawStatus : 'OFFLINE';
                    $activeDur = (int) ($slot['active_duration'] ?? $slot['duration_seconds'] ?? 0);
                @endphp
                <div class="rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300 {{ $status === 'ACTIVE' ? 'border-mint/40 bg-surface2' : ($status === 'PAUSED_OVERHEAT' ? 'border-amber/40 bg-surface2' : '') }}" id="card-slot-{{ $i }}">
                    <!-- Header Slot -->
                    <div class="flex items-center justify-between">
                        <p class="font-mono text-sm font-semibold text-white tracking-wide">SLOT 0{{ $i }}</p>
                        <span class="font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md uppercase tracking-wide {{ $status === 'ACTIVE' ? 'bg-mint/10 border border-mint/25 text-mint' : ($status === 'WAITING_PAYMENT' ? 'bg-amber/10 border border-amber/25 text-amber pulse-amber' : ($status === 'PAUSED_OVERHEAT' ? 'bg-amber/15 border border-amber/35 text-amber pulse-amber' : 'bg-surface2 border border-hairline text-neutral-300')) }}" id="badge-slot-{{ $i }}">
                            @if ($status === 'ACTIVE')
                                AKTIF
                            @elseif ($status === 'WAITING_PAYMENT')
                                MENUNGGU BAYAR
                            @elseif ($status === 'PAUSED_OVERHEAT')
                                DIJEDA (SUHU TINGGI)
                            @elseif ($status === 'OFFLINE')
                                OFFLINE
                            @else
                                SIAP
                            @endif
                        </span>
                    </div>

                    <!-- Center Countdown Display -->
                    <div class="my-3 text-center">
                        <p class="font-mono text-[11px] font-medium uppercase tracking-widest text-neutral-300">Sisa Waktu Sewa</p>
                        <p class="mt-1 font-mono text-4xl font-bold tracking-tight {{ $status === 'ACTIVE' ? 'text-mint' : ($status === 'PAUSED_OVERHEAT' || $status === 'WAITING_PAYMENT' ? 'text-amber' : 'text-neutral-300') }}" id="countdown-slot-{{ $i }}" aria-live="off" aria-atomic="true">
                            @if ($status === 'ACTIVE')
                                {{ $activeDur == 0 ? 'Menghitung…' : '--:--' }}
                            @elseif ($status === 'WAITING_PAYMENT')
                                SCAN QR
                            @elseif ($status === 'PAUSED_OVERHEAT')
                                DIJEDA
                            @elseif ($status === 'OFFLINE')
                                --:--
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
                            @elseif ($status === 'PAUSED_OVERHEAT')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-amber/15 text-amber border border-amber/30 animate-pulse">
                                    <i class="fa-solid fa-temperature-arrow-down mr-1.5 text-xs" aria-hidden="true"></i> Pendinginan Termal (Relay OFF)
                                </span>
                            @elseif ($status === 'OFFLINE')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono text-neutral-400 border border-hairline">
                                    <i class="fa-solid fa-power-off mr-1.5 text-xs" aria-hidden="true"></i> Steker Terputus
                                </span>
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
                                @elseif ($status === 'PAUSED_OVERHEAT')
                                    Dibekukan
                                @elseif ($status === 'WAITING_PAYMENT')
                                    Menunggu
                                @elseif ($status === 'OFFLINE')
                                    Perangkat Terputus
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
                                @elseif ($status === 'PAUSED_OVERHEAT')
                                    Tersimpan
                                @elseif ($status === 'WAITING_PAYMENT')
                                    Menunggu
                                @elseif ($status === 'OFFLINE')
                                    -
                                @else
                                    Rp 0
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Action Buttons: Kontrol Admin (Nyalakan Manual / Matikan) -->
                    <div class="mt-4" id="slot-actions-{{ $i }}">
                        @if ($status === 'ACTIVE' || $status === 'PAUSED_OVERHEAT')
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
                        @elseif ($status === 'OFFLINE')
                            <button type="button" disabled class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-4 rounded-lg bg-surface2/60 border border-hairline text-neutral-400 cursor-not-allowed flex items-center justify-center space-x-1.5">
                                <i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i>
                                <span>ESP32 Tidak Terhubung</span>
                            </button>
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
                    {{ number_format(($isEsp32Online ?? false) ? ($firebaseData['sensors']['voltage'] ?? 0) : 0, 1) }} <span class="text-xs font-normal text-neutral-300">V</span>
                </p>
            </div>
            <!-- Arus -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Arus</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-curr">
                    {{ number_format(($isEsp32Online ?? false) ? ($firebaseData['sensors']['current'] ?? 0) : 0, 2) }} <span class="text-xs font-normal text-neutral-300">A</span>
                </p>
            </div>
            <!-- Daya Aktif -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Daya Aktif</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-power">
                    {{ number_format(($isEsp32Online ?? false) ? ($firebaseData['sensors']['power'] ?? 0) : 0, 1) }} <span class="text-xs font-normal text-neutral-300">W</span>
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
                    {{ number_format(($isEsp32Online ?? false) ? ($firebaseData['sensors']['temperature'] ?? 0) : 0, 1) }}°C
                </p>
                <div class="mt-1" id="temp-badge">
                    @if($isEsp32Online ?? false)
                        <span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-mint/10 text-mint border border-mint/25">Normal &lt; {{ round($overheatThreshold) }}°C</span>
                    @else
                        <span class="inline-block font-mono text-[11px] font-medium px-1.5 py-0.5 rounded bg-surface2 text-neutral-300 border border-hairline">Offline</span>
                    @endif
                </div>
            </div>
            <!-- Kelembapan -->
            <div class="px-4 py-2 md:py-0">
                <p class="text-xs text-neutral-300 font-medium">Kelembapan</p>
                <p class="font-mono text-lg text-white font-semibold mt-1" id="sensor-hum">
                    {{ round(($isEsp32Online ?? false) ? ($firebaseData['sensors']['humidity'] ?? 0) : 0) }}%
                </p>
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/mqtt@5.3.5/dist/mqtt.min.js"></script>
<script>
    window.hasCustomDashboardLoop = true;
    const OVERHEAT_LIMIT = {{ $overheatThreshold }};
    const CSRF_TOKEN = '{{ csrf_token() }}';
    const ESP32_OFFLINE_THRESHOLD_MS = 25000;
    const ESP32_OFFLINE_THRESHOLD_S = Math.round(ESP32_OFFLINE_THRESHOLD_MS / 1000);

    let lastSensorUpdatedAt = 0;
    let isEsp32CurrentlyOnline = {{ ($isEsp32Online ?? false) ? 'true' : 'false' }};
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

        if (status === 'OFFLINE') {
            return `
                <button type="button" disabled class="w-full min-h-[44px] text-xs font-semibold py-2.5 px-4 rounded-lg bg-surface2/60 border border-hairline text-neutral-400 cursor-not-allowed flex items-center justify-center space-x-1.5">
                    <i class="fa-solid fa-plug-circle-xmark" aria-hidden="true"></i>
                    <span>ESP32 Tidak Terhubung</span>
                </button>
            `;
        }

        if (status === 'ACTIVE' || status === 'PAUSED_OVERHEAT') {
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
        if (lastSlotStatus[i] === status) return;
        lastSlotStatus[i] = status;
        const actionsEl = document.getElementById('slot-actions-' + i);
        if (actionsEl) actionsEl.innerHTML = slotActionTemplate(i, status);
    }

    function getEsp32Badge() {
        return document.getElementById('esp32-badge');
    }

    function setEsp32Online() {
        isEsp32CurrentlyOnline = true;
        const badge = getEsp32Badge();
        if (badge) {
            badge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-mint/10 text-mint';
            badge.setAttribute('title', 'ESP32 online — telemetri realtime MQTT');
            badge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">ESP32</span><span class="sr-only">ESP32 online</span>';
        }
    }

    function setEsp32Offline() {
        isEsp32CurrentlyOnline = false;
        const badge = getEsp32Badge();
        if (badge) {
            badge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-400';
            badge.setAttribute('title', 'ESP32 offline — data sensor basi / steker terputus');
            badge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">ESP32 Offline</span><span class="sr-only">ESP32 offline</span>';
        }
        // Nol-kan seluruh nilai telemetri sensor
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
        // Matikan seluruh slot dan timer runaway saat steker dicabut
        setSlotsOffline();
    }

    function setSlotsOffline() {
        for (let i = 1; i <= 3; i++) {
            const card = document.getElementById('card-slot-' + i);
            const badge = document.getElementById('badge-slot-' + i);
            const countdown = document.getElementById('countdown-slot-' + i);
            const duration = document.getElementById('duration-slot-' + i);
            const paid = document.getElementById('paid-slot-' + i);
            const loadStatusEl = document.getElementById('load-status-slot-' + i);

            if (!card) continue;

            card.className = 'rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300 opacity-75';
            if (badge) {
                badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-surface2 border border-hairline text-neutral-400 uppercase tracking-wide';
                badge.innerText = 'OFFLINE';
            }
            if (countdown) {
                countdown.innerText = '--:--';
                countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-400';
            }
            if (duration) duration.innerText = 'Perangkat Terputus';
            if (paid) paid.innerText = '-';
            if (loadStatusEl) {
                loadStatusEl.innerHTML = '<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono text-neutral-400 border border-hairline"><i class="fa-solid fa-power-off mr-1.5 text-xs" aria-hidden="true"></i> Steker Terputus</span>';
            }
            renderSlotActions(i, 'OFFLINE');
        }
    }

    function updateSensorDisplay(sensors) {
        if (!sensors || !document.getElementById('sensor-temp')) return;

        const temp = parseFloat(sensors.temperature || 0);
        const hum  = parseFloat(sensors.humidity || 0);
        const volt = parseFloat(sensors.voltage || 0);
        const curr = parseFloat(sensors.current || 0);
        const pwr  = parseFloat(sensors.power || 0);
        const enrg = parseFloat(sensors.energy || 0);

        document.getElementById('sensor-temp').innerText = temp.toFixed(1) + '°C';
        document.getElementById('sensor-hum').innerText = Math.round(hum) + '%';
        document.getElementById('sensor-volt').innerHTML = volt.toFixed(1) + ' <span class="text-xs text-neutral-400 font-normal">V</span>';
        document.getElementById('sensor-curr').innerHTML = curr.toFixed(2) + ' <span class="text-xs text-neutral-400 font-normal">A</span>';
        document.getElementById('sensor-power').innerHTML = pwr.toFixed(1) + ' <span class="text-xs text-neutral-400 font-normal">W</span>';
        document.getElementById('sensor-energy').innerText = enrg.toFixed(4);

        const tempBadge = document.getElementById('temp-badge');
        if (tempBadge) {
            if (temp >= OVERHEAT_LIMIT && OVERHEAT_LIMIT > 0) {
                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-crimson/20 text-crimson border border-crimson/30">OVERHEAT!</span>';
            } else if (temp > 0) {
                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-mint/10 text-mint border border-mint/25">Normal &lt; ' + Math.round(OVERHEAT_LIMIT) + '°C</span>';
            } else {
                tempBadge.innerHTML = '<span class="inline-block font-mono text-[11px] font-semibold px-1.5 py-0.5 rounded bg-surface2 text-neutral-300 border border-hairline">Standby</span>';
            }
        }
    }

    function applyMqttSlotState(slotNum, slotData) {
        // Penerimaan status slot dari MQTT adalah bukti valid bahwa ESP32 menyala
        setEsp32Online();
        lastMqttPacketTime = Date.now();

        const status = (slotData.status || 'STANDBY').toUpperCase();
        const card = document.getElementById('card-slot-' + slotNum);
        const badge = document.getElementById('badge-slot-' + slotNum);
        const countdown = document.getElementById('countdown-slot-' + slotNum);
        const duration = document.getElementById('duration-slot-' + slotNum);
        const paid = document.getElementById('paid-slot-' + slotNum);
        const loadStatusEl = document.getElementById('load-status-slot-' + slotNum);

        if (!card) return;

        if (status === 'ACTIVE') {
            badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-mint/10 border border-mint/25 text-mint uppercase tracking-wide';
            badge.innerText = 'AKTIF';
            card.className = 'rounded-xl bg-surface2 border border-mint/40 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';

            const remainingSec = parseInt(slotData.remaining_sec) || 0;
            const durationSec = parseInt(slotData.duration_sec) || 0;

            if (durationSec === 0) {
                countdown.innerText = 'Menyala (Manual)';
                countdown.className = 'mt-1 font-mono text-2xl font-bold tracking-tight text-amber';
                duration.innerText = 'Kontinu (Tanpa Batas)';
                paid.innerText = 'Gratis (Rp 0)';
            } else {
                const m = Math.floor(remainingSec / 60).toString().padStart(2, '0');
                const s = (remainingSec % 60).toString().padStart(2, '0');
                countdown.innerText = `${m}:${s}`;
                countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-mint';
                duration.innerText = Math.round(durationSec / 60) + ' Menit';
                paid.innerText = 'Rp ' + (slotData.amount ? Number(slotData.amount).toLocaleString('id-ID') : '1.000');
            }
            renderSlotActions(slotNum, status);
        }
        else if (status === 'PAUSED_OVERHEAT') {
            badge.className = 'pulse-amber font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-amber/15 border border-amber/35 text-amber uppercase tracking-wide';
            badge.innerText = 'DIJEDA (SUHU TINGGI)';
            card.className = 'rounded-xl bg-surface2 border border-amber/40 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';

            const remainingSec = parseInt(slotData.remaining_sec) || parseInt(slotData.duration_sec) || 0;
            if (remainingSec > 0) {
                const m = Math.floor(remainingSec / 60).toString().padStart(2, '0');
                const s = (remainingSec % 60).toString().padStart(2, '0');
                countdown.innerText = `${m}:${s} [PAUSED]`;
            } else {
                countdown.innerText = 'DIJEDA [SUHU]';
            }
            countdown.className = 'mt-1 font-mono text-2xl font-bold tracking-tight text-amber';
            duration.innerText = (slotData.duration_sec ? Math.round(slotData.duration_sec / 60) + ' Menit' : 'Kontinu') + ' (Dibekukan)';
            paid.innerText = 'Tersimpan';

            if (loadStatusEl) {
                loadStatusEl.innerHTML = `
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-amber/15 text-amber border border-amber/30 animate-pulse" title="Relay dimatikan sementara untuk pendinginan termal.">
                        <i class="fa-solid fa-temperature-arrow-down mr-1.5 text-xs" aria-hidden="true"></i> Pendinginan Termal (Relay OFF)
                    </span>
                `;
            }
            renderSlotActions(slotNum, status);
        }
        else if (status === 'WAITING_PAYMENT') {
            if (loadStatusEl) loadStatusEl.innerHTML = '';
            badge.className = 'pulse-amber font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-amber/10 border border-amber/25 text-amber uppercase tracking-wide';
            badge.innerText = 'MENUNGGU BAYAR';
            card.className = 'rounded-xl bg-surface border border-amber/30 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
            countdown.innerText = 'SCAN QR';
            countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-amber';
            duration.innerText = 'Menunggu';
            paid.innerText = 'Menunggu';
            renderSlotActions(slotNum, status);
        }
        else {
            if (loadStatusEl) loadStatusEl.innerHTML = '';
            badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-surface2 border border-hairline text-neutral-300 uppercase tracking-wide';
            badge.innerText = 'SIAP';
            card.className = 'rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
            countdown.innerText = '00:00';
            countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-300';
            duration.innerText = 'Standby';
            paid.innerText = 'Rp 0';
            renderSlotActions(slotNum, status);
        }
    }

    // ============================================================
    // EMQX CLOUD MQTT WEBSOCKET CLIENT (REALTIME SUB-DETIK)
    // ============================================================
    const MQTT_WSS_URL = 'wss://o2f6eb8b.ala.asia-southeast1.emqxsl.com:8084/mqtt';
    let mqttWssClient = null;
    let lastMqttPacketTime = 0; // Timestamp paket MQTT terakhir dari perangkat

    function initMqttWss() {
        if (typeof mqtt === 'undefined') {
            console.warn('[MQTT WSS] Library mqtt.js belum termuat, mengandalkan HTTP fallback');
            return;
        }

        try {
            mqttWssClient = mqtt.connect(MQTT_WSS_URL, {
                username: 'ranova_esp32',
                password: '02082006',
                clientId: 'ranova_web_' + Math.random().toString(16).substring(2, 8),
                clean: true,
                connectTimeout: 5000,
                reconnectPeriod: 4000
            });

            mqttWssClient.on('connect', () => {
                console.log('[MQTT WSS] Terhubung ke EMQX Broker (WebSocket 8084)');
                mqttWssClient.subscribe('ranova/device_001/#', { qos: 0 });

                const mqttBadge = document.getElementById('mqtt-badge');
                if (mqttBadge) {
                    mqttBadge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-mint/10 text-mint';
                    mqttBadge.setAttribute('title', 'MQTT Broker (EMQX Cloud) terhubung');
                    mqttBadge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">MQTT</span><span class="sr-only">MQTT terhubung</span>';
                }
            });

            mqttWssClient.on('message', (topic, message) => {
                try {
                    const payload = JSON.parse(message.toString());
                    lastMqttPacketTime = Date.now();

                    // 1. Status ESP32 Online / Offline (LWT)
                    if (topic === 'ranova/device_001/status') {
                        if (payload.state === 'offline') {
                            setEsp32Offline();
                        } else if (payload.state === 'online') {
                            setEsp32Online();
                        }
                    }
                    // 2. Telemetri Sensor Realtime
                    else if (topic === 'ranova/device_001/telemetry') {
                        setEsp32Online();
                        lastSensorUpdatedAt = payload.updated_at || Math.floor(Date.now() / 1000);
                        updateSensorDisplay(payload);
                    }
                    // 3. Status Tiap Slot Stop Kontak
                    else if (topic.startsWith('ranova/device_001/slots/')) {
                        const parts = topic.split('/');
                        const slotNum = parseInt(parts[3], 10);
                        if (slotNum >= 1 && slotNum <= 3) {
                            applyMqttSlotState(slotNum, payload);
                        }
                    }
                    // 4. Seleksi Tombol Fisik (Waiting Payment)
                    else if (topic === 'ranova/device_001/selection') {
                        setEsp32Online();
                        if (payload.status === 'WAITING_PAYMENT' && payload.slot_num >= 1 && payload.slot_num <= 3) {
                            applyMqttSlotState(payload.slot_num, { status: 'WAITING_PAYMENT' });
                        }
                    }
                } catch (err) {
                    console.error('[MQTT Message Error]:', err);
                }
            });

            mqttWssClient.on('error', (err) => {
                console.warn('[MQTT WSS Error]:', err.message);
                const mqttBadge = document.getElementById('mqtt-badge');
                if (mqttBadge) {
                    mqttBadge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-400';
                    mqttBadge.setAttribute('title', 'MQTT Broker terputus: ' + err.message);
                    mqttBadge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">MQTT Terputus</span><span class="sr-only">MQTT terputus</span>';
                }
            });

            mqttWssClient.on('offline', () => {
                console.warn('[MQTT WSS] Offline, beralih ke HTTP polling fallback...');
                const mqttBadge = document.getElementById('mqtt-badge');
                if (mqttBadge) {
                    mqttBadge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-400';
                    mqttBadge.setAttribute('title', 'MQTT Broker terputus');
                    mqttBadge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">MQTT Terputus</span><span class="sr-only">MQTT terputus</span>';
                }
            });
        } catch (e) {
            console.warn('[MQTT WSS Init Exception]:', e);
        }
    }

    // Watchdog Timer: Jika tidak ada satupun pesan MQTT dari ESP32 selama >15 detik, tandai offline
    setInterval(() => {
        if (mqttWssClient && mqttWssClient.connected) {
            if (isEsp32CurrentlyOnline && lastMqttPacketTime > 0 && (Date.now() - lastMqttPacketTime >= 15000)) {
                console.warn('[MQTT Watchdog] Tidak ada data dari ESP32 selama >15 detik. Menandai ESP32 offline.');
                setEsp32Offline();
            }
        }
    }, 3000);

    // Helper untuk mem-publish perintah MQTT seketika dari tombol Dashboard
    function sendMqttCommand(action, slot, duration = 0) {
        if (mqttWssClient && mqttWssClient.connected) {
            const cmd = JSON.stringify({ action, slot, duration });
            mqttWssClient.publish('ranova/device_001/command', cmd, { qos: 1 });
            console.log('[MQTT WSS Published Command]:', cmd);
        }
    }

    // Pasang listener intersep pada form kontrol admin untuk publish MQTT langsung & background AJAX
    document.addEventListener('submit', (e) => {
        // Jika aksi dicegah oleh modal konfirmasi sebelum diklik 'Ya', jangan kirim perintah MQTT!
        if (e.defaultPrevented) return;

        const form = e.target.closest('form');
        if (!form) return;

        // Jangan kirim perintah jika form membutuhkan konfirmasi dan belum dikonfirmasi oleh pengguna
        if (form.classList.contains('js-confirm-form') && form.dataset.confirmed !== '1') {
            return;
        }

        const actionUrl = form.getAttribute('action') || '';

        const startMatch = actionUrl.match(/\/dashboard\/slots\/(\d+)\/start/);
        const stopMatch = actionUrl.match(/\/dashboard\/slots\/(\d+)\/stop/);
        const isEmergency = actionUrl.includes('emergency-stop') || actionUrl.includes('emergency_stop');

        if (startMatch || stopMatch || isEmergency) {
            // Hentikan full-page browser reload!
            e.preventDefault();

            // 1. Jalur Cepat (Fast-Path) MQTT: Eksekusi fisik relay ESP32 dalam < 50ms
            if (startMatch) {
                const slotNum = parseInt(startMatch[1], 10);
                sendMqttCommand('START', slotNum, 0);
            } else if (stopMatch) {
                const slotNum = parseInt(stopMatch[1], 10);
                sendMqttCommand('STOP', slotNum);
            } else if (isEmergency) {
                sendMqttCommand('EMERGENCY', 0);
            }

            // 2. Jalur Latar Belakang (Background AJAX): Kirim ke Laravel & Firebase secara asinkron
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }

            fetch(actionUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (typeof window.showToast === 'function') {
                    window.showToast(data.message || 'Perintah berhasil diproses.', data.success ? 'success' : 'error');
                }
            })
            .catch(err => {
                console.warn('[AJAX Background Sync Error]:', err);
                if (typeof window.showToast === 'function') {
                    window.showToast('Perintah terkirim ke ESP32. (Sinkronisasi database sedang diproses)', 'info');
                }
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            });
        }
    });

    // ============================================================
    // HTTP POLLING FALLBACK (Setiap 2 Detik - Menjamin Sinkronisasi)
    // ============================================================
    let isDashboardPolling = false;
    let dashboardBackoffMs = 2000;
    let dashboardPollTimer = null;

    function scheduleNextDashboardPoll(delay) {
        if (dashboardPollTimer) clearTimeout(dashboardPollTimer);
        dashboardPollTimer = setTimeout(updateDashboard, delay);
    }

    function updateDashboard() {
        if (isDashboardPolling) {
            scheduleNextDashboardPoll(dashboardBackoffMs);
            return;
        }
        isDashboardPolling = true;

        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timeoutId = controller ? setTimeout(() => controller.abort(), 8000) : null;
        const fetchOpts = controller ? { signal: controller.signal } : {};

        fetch('{{ route('dashboard.realtime') }}', fetchOpts)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (timeoutId) clearTimeout(timeoutId);
                isDashboardPolling = false;
                dashboardBackoffMs = 2000;

                const now = new Date();
                const timeStr = now.toTimeString().split(' ')[0];
                const syncEl = document.getElementById('last-sync-time');
                if (syncEl) syncEl.innerText = 'Sinkron: ' + timeStr;

                // Cek apakah MQTT WebSocket sedang aktif streaming (< 15 detik terakhir)
                const isMqttStreaming = (mqttWssClient && mqttWssClient.connected && (Date.now() - lastMqttPacketTime < 15000));

                if (isMqttStreaming) {
                    // MQTT AKTIF: MQTT adalah Single Source of Truth untuk status perangkat & slot realtime.
                    // Jangan biarkan polling HTTP menimpa status online atau slot menjadi OFFLINE!
                    scheduleNextDashboardPoll(dashboardBackoffMs);
                    return;
                }

                // JIKA MQTT TIDAK AKTIF / TERPUTUS (Fallback Polling Mode):
                // 1. Cek Apakah ESP32 Sedang Offline (Steker dicabut)
                if (data.is_esp32_online === false) {
                    setEsp32Offline();
                    scheduleNextDashboardPoll(dashboardBackoffMs);
                    return;
                }

                setEsp32Online();

                // 2. Update 3 Slot Stop Kontak
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

                    if (!card) continue;

                    if (status === 'ACTIVE') {
                        badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-mint/10 border border-mint/25 text-mint uppercase tracking-wide';
                        badge.innerText = 'AKTIF';
                        card.className = 'rounded-xl bg-surface2 border border-mint/40 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';

                        renderSlotActions(i, status);

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
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-mint/15 text-mint border border-mint/30" title="Hanya 1 slot aktif, angka ini milik slot tersebut.">
                                            <i class="fa-solid fa-bolt mr-1.5 text-xs" aria-hidden="true"></i> Beban Slot: ${currentPzemPower.toFixed(1)} W (Tunggal)
                                        </span>
                                    `;
                                } else {
                                    loadStatusEl.innerHTML = `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-mint/15 text-mint border border-mint/30" title="Daya total gabungan seluruh slot aktif.">
                                            <i class="fa-solid fa-bolt mr-1.5 text-xs" aria-hidden="true"></i> Total Stasiun: ${currentPzemPower.toFixed(1)} W (Agregat ${activeSlotsCount} Slot)
                                        </span>
                                    `;
                                }
                            }
                        }

                        const startedAt = parseInt(slot.started_at) || 0;
                        const durationSec = parseInt(slot.active_duration) || 0;
                        const nowSec = Math.floor(Date.now() / 1000);
                        const elapsed = nowSec - startedAt;

                        if (durationSec === 0) {
                            const safeStartedAt = startedAt > 0 ? startedAt : nowSec;
                            const manualElapsed = Math.max(0, nowSec - safeStartedAt);
                            countdown.innerText = 'Menyala: ' + fmtElapsed(manualElapsed) + ' (Manual)';
                            countdown.className = 'mt-1 font-mono text-2xl font-bold tracking-tight text-amber';
                            duration.innerText = 'Kontinu (Tanpa Batas)';
                            paid.innerText = 'Gratis (Rp 0)';
                            if (loadStatusEl && manualElapsed > 3600) {
                                loadStatusEl.innerHTML += `
                                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-amber/15 text-amber border border-amber/40 animate-pulse" role="alert" title="Slot manual menyala lebih dari 1 jam.">
                                        <i class="fa-solid fa-triangle-exclamation mr-1.5 text-xs" aria-hidden="true"></i> Menyala &gt; 1 Jam
                                    </span>
                                `;
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
                    }
                    else if (status === 'PAUSED_OVERHEAT') {
                        badge.className = 'pulse-amber font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-amber/15 border border-amber/35 text-amber uppercase tracking-wide';
                        badge.innerText = 'DIJEDA (SUHU TINGGI)';
                        card.className = 'rounded-xl bg-surface2 border border-amber/40 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';

                        const remainingSec = parseInt(slot.remaining_sec) || parseInt(slot.duration_sec) || parseInt(slot.active_duration) || 0;
                        if (remainingSec > 0) {
                            const m = Math.floor(remainingSec / 60).toString().padStart(2, '0');
                            const s = (remainingSec % 60).toString().padStart(2, '0');
                            countdown.innerText = `${m}:${s} [PAUSED]`;
                        } else {
                            countdown.innerText = 'DIJEDA [SUHU]';
                        }
                        countdown.className = 'mt-1 font-mono text-2xl font-bold tracking-tight text-amber';
                        duration.innerText = (slot.duration_sec || slot.active_duration ? Math.round((slot.duration_sec || slot.active_duration) / 60) + ' Menit' : 'Kontinu') + ' (Dibekukan)';
                        paid.innerText = 'Tersimpan';

                        if (loadStatusEl) {
                            loadStatusEl.innerHTML = `
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono font-medium bg-amber/15 text-amber border border-amber/30 animate-pulse" title="Relay dimatikan sementara untuk pendinginan termal.">
                                    <i class="fa-solid fa-temperature-arrow-down mr-1.5 text-xs" aria-hidden="true"></i> Pendinginan Termal (Relay OFF)
                                </span>
                            `;
                        }
                        renderSlotActions(i, status);
                    }
                    else if (status === 'WAITING_PAYMENT') {
                        if (loadStatusEl) loadStatusEl.innerHTML = '';
                        badge.className = 'pulse-amber font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-amber/10 border border-amber/25 text-amber uppercase tracking-wide';
                        badge.innerText = 'MENUNGGU BAYAR';
                        card.className = 'rounded-xl bg-surface border border-amber/30 p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        countdown.innerText = 'SCAN QR';
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-amber';
                        duration.innerText = 'Menunggu';
                        paid.innerText = 'Menunggu';
                        renderSlotActions(i, status);
                    }
                    else if (status === 'OFFLINE') {
                        setSlotsOffline();
                    }
                    else {
                        if (loadStatusEl) loadStatusEl.innerHTML = '';
                        badge.className = 'font-mono text-[11px] font-medium px-2.5 py-0.5 rounded-md bg-surface2 border border-hairline text-neutral-300 uppercase tracking-wide';
                        badge.innerText = 'SIAP';
                        card.className = 'rounded-xl bg-surface border border-hairline p-5 flex flex-col justify-between min-h-[250px] transition-all duration-300';
                        countdown.innerText = '00:00';
                        countdown.className = 'mt-1 font-mono text-4xl font-bold tracking-tight text-neutral-300';
                        duration.innerText = 'Standby';
                        paid.innerText = 'Rp 0';
                        renderSlotActions(i, status);
                    }
                }

                // 3. Update Sensor Fisik
                if (data.sensors) {
                    const incomingUpdatedAt = parseInt(data.sensors.updated_at) || 0;
                    if (incomingUpdatedAt > lastSensorUpdatedAt) {
                        lastSensorUpdatedAt = incomingUpdatedAt;
                    }
                    updateSensorDisplay(data.sensors);
                }

                scheduleNextDashboardPoll(dashboardBackoffMs);
            })
            .catch(err => {
                if (timeoutId) clearTimeout(timeoutId);
                isDashboardPolling = false;
                dashboardBackoffMs = Math.min(dashboardBackoffMs * 2, 8000);
                console.warn('Realtime sync pause, backoff ' + dashboardBackoffMs + 'ms:', err.name === 'AbortError' ? 'Timeout' : err.message);
                const syncEl = document.getElementById('last-sync-time');
                if (syncEl) syncEl.innerText = 'Koneksi terputus...';
                scheduleNextDashboardPoll(dashboardBackoffMs);
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initMqttWss();
        updateDashboard();
    });
</script>
@endpush
