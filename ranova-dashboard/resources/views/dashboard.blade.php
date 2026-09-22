@extends('layouts.app')

@section('title', 'Dashboard Realtime')
@section('page_title', 'Pemantauan Stasiun Pengisian Daya')

@section('content')
<div class="space-y-6">

    <!-- 1. Ringkasan Finansial Hari Ini -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Pendapatan QRIS -->
        <div class="glass-card rounded-xl p-5 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400">Pendapatan QRIS (Hari Ini)</p>
                    <p class="text-2xl font-bold text-emerald-400 mt-1">Rp {{ number_format($todayRevenue, 0, ',', '.') }}</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Total Keseluruhan: Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-teal-400"></div>
        </div>

        <!-- Total Energi PLN (kWh) -->
        <div class="glass-card rounded-xl p-5 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400">Total Energi Terpakai</p>
                    <p class="text-2xl font-bold text-cyan-400 mt-1" id="stat-energy">{{ number_format($totalKwh, 3, ',', '.') }} <span class="text-sm font-normal text-slate-400">kWh</span></p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Sensor PZEM-004T Stasiun</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400 text-lg">
                    <i class="fa-solid fa-plug-circle-bolt"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-cyan-500 to-blue-400"></div>
        </div>

        <!-- Biaya Listrik PLN -->
        <div class="glass-card rounded-xl p-5 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400">Estimasi Biaya PLN</p>
                    <p class="text-2xl font-bold text-amber-400 mt-1" id="stat-pln-cost">Rp {{ number_format($totalPlnCost, 0, ',', '.') }}</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Tarif: Rp 1.444,70 / kWh</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-500 to-yellow-400"></div>
        </div>

        <!-- Laba Bersih -->
        <div class="glass-card rounded-xl p-5 relative overflow-hidden">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-400">Estimasi Laba Bersih</p>
                    <p class="text-2xl font-bold {{ $netProfit >= 0 ? 'text-emerald-400' : 'text-rose-400' }} mt-1">
                        Rp {{ number_format($netProfit, 0, ',', '.') }}
                    </p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Pendapatan - Biaya Listrik</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
                    <i class="fa-solid fa-sack-dollar"></i>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-green-600"></div>
        </div>
    </div>

    <!-- 2. Kartu Status 3 Slot Stop Kontak (Realtime Live Sync) -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 flex items-center space-x-2">
                <i class="fa-solid fa-server text-emerald-400"></i>
                <span>Status 3 Stop Kontak Broco (Relay Kontrol)</span>
            </h2>
            <span class="text-xs text-slate-500" id="last-sync-time">Menyinkronkan...</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @for ($i = 1; $i <= 3; $i++)
                @php
                    $slotKey = 'slot' . $i;
                    $slot = $firebaseData[$slotKey] ?? ['status' => 'STANDBY', 'active_duration' => 0, 'started_at' => 0];
                    $status = strtoupper($slot['status'] ?? 'STANDBY');
                @endphp
                <div class="glass-card rounded-2xl p-6 relative flex flex-col justify-between transition-all duration-300 border border-slate-800" id="card-slot-{{ $i }}">
                    <div>
                        <!-- Header Kartu -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-sm font-extrabold text-slate-200 border border-slate-700" id="icon-slot-{{ $i }}">
                                    0{{ $i }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-100 text-base">Stop Kontak {{ $i }}</h3>
                                    <p class="text-[11px] text-slate-400">Broco Inbow Slot {{ $i }}</p>
                                </div>
                            </div>
                            <!-- Status Badge -->
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold tracking-wide transition" id="badge-slot-{{ $i }}">
                                {{ $status }}
                            </span>
                        </div>

                        <!-- Display Countdown / State -->
                        <div class="my-6 text-center py-5 rounded-xl bg-slate-950/60 border border-slate-800/80">
                            <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold mb-1" id="label-timer-slot-{{ $i }}">
                                Sisa Waktu Sewa
                            </p>
                            <p class="text-3xl font-mono font-extrabold tracking-tight text-slate-100" id="countdown-slot-{{ $i }}">
                                --:--
                            </p>
                        </div>

                        <!-- Mini Info -->
                        <div class="space-y-2 text-xs border-t border-slate-800/60 pt-4 mb-4">
                            <div class="flex justify-between text-slate-400">
                                <span>Durasi Dibeli:</span>
                                <span class="font-semibold text-slate-200" id="duration-slot-{{ $i }}">-</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span>Total Pembayaran:</span>
                                <span class="font-semibold text-emerald-400" id="paid-slot-{{ $i }}">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <div id="action-slot-{{ $i }}">
                        <form action="{{ route('dashboard.slots.stop', $i) }}" method="POST" onsubmit="return confirm('Hentikan sewa pada Slot {{ $i }} sekarang?')">
                            @csrf
                            <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 transition flex items-center justify-center space-x-1.5 disabled:opacity-30 disabled:cursor-not-allowed" id="btn-stop-slot-{{ $i }}">
                                <i class="fa-solid fa-power-off"></i>
                                <span>Matikan Manual</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endfor
        </div>
    </div>

    <!-- 3. Telemetri Sensor Realtime (PZEM-004T & DHT22) -->
    <div>
        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center space-x-2">
            <i class="fa-solid fa-chart-simple text-cyan-400"></i>
            <span>Telemetri Sensor Fisik (Stasiun Utama)</span>
        </h2>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
            <!-- Suhu Box DHT22 -->
            <div class="glass-card rounded-xl p-4 text-center relative">
                <p class="text-xs text-slate-400 mb-1">Suhu Internal Box</p>
                <p class="text-2xl font-bold text-amber-400" id="sensor-temp">{{ number_format($latestTemp->temperature ?? 0.0, 1) }}°C</p>
                <p class="text-[10px] text-slate-500 mt-1">Maks {{ $overheatThreshold }}°C</p>
                <div class="mt-2" id="temp-badge">
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">Standby</span>
                </div>
            </div>

            <!-- Kelembaban DHT22 -->
            <div class="glass-card rounded-xl p-4 text-center">
                <p class="text-xs text-slate-400 mb-1">Kelembaban Udara</p>
                <p class="text-2xl font-bold text-blue-400" id="sensor-hum">{{ number_format($latestTemp->humidity ?? 0.0, 0) }}%</p>
                <p class="text-[10px] text-slate-500 mt-1">Sensor DHT22</p>
                <div class="mt-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">Standby</span>
                </div>
            </div>

            <!-- Tegangan AC PZEM -->
            <div class="glass-card rounded-xl p-4 text-center">
                <p class="text-xs text-slate-400 mb-1">Tegangan Listrik</p>
                <p class="text-2xl font-bold text-slate-100" id="sensor-volt">{{ number_format($latestPower->voltage ?? 0.0, 1) }} <span class="text-xs text-slate-400 font-normal">V</span></p>
                <p class="text-[10px] text-slate-500 mt-1">PLN 220V 50Hz</p>
                <div class="mt-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">Standby</span>
                </div>
            </div>

            <!-- Arus Listrik PZEM -->
            <div class="glass-card rounded-xl p-4 text-center">
                <p class="text-xs text-slate-400 mb-1">Arus Beban Total</p>
                <p class="text-2xl font-bold text-slate-100" id="sensor-curr">{{ number_format($latestPower->current ?? 0.0, 2) }} <span class="text-xs text-slate-400 font-normal">A</span></p>
                <p class="text-[10px] text-slate-500 mt-1">Donat CT PZEM</p>
                <div class="mt-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-300">Realtime</span>
                </div>
            </div>

            <!-- Daya Nyata PZEM -->
            <div class="glass-card rounded-xl p-4 text-center">
                <p class="text-xs text-slate-400 mb-1">Daya Nyata</p>
                <p class="text-2xl font-bold text-emerald-400" id="sensor-power">{{ number_format($latestPower->power ?? 0.0, 1) }} <span class="text-xs text-slate-400 font-normal">W</span></p>
                <p class="text-[10px] text-slate-500 mt-1">Beban Aktif</p>
                <div class="mt-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">Watt</span>
                </div>
            </div>

            <!-- Total kWh PZEM -->
            <div class="glass-card rounded-xl p-4 text-center">
                <p class="text-xs text-slate-400 mb-1">Akumulasi Energi</p>
                <p class="text-2xl font-bold text-cyan-400" id="sensor-energy">{{ number_format($latestPower->energy ?? 0.125, 4) }}</p>
                <p class="text-[10px] text-slate-500 mt-1">Total kWh Meter</p>
                <div class="mt-2">
                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-cyan-500/10 text-cyan-400">kWh</span>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    // Polling Realtime Engine (setiap 2 detik sinkron dengan Firebase & Database)
    const OVERHEAT_LIMIT = {{ $overheatThreshold }};
    const ESP32_OFFLINE_THRESHOLD_MS = 10000; // 10 detik tanpa update = offline

    // Menyimpan Unix timestamp (seconds) dari updated_at terakhir yang diterima
    let lastSensorUpdatedAt = 0;

    // Elemen badge status ESP32 (dibuat dinamis di bawah)
    function getOrCreateEsp32Badge() {
        let badge = document.getElementById('esp32-status-badge');
        if (!badge) {
            const container = document.getElementById('sensor-temp')?.closest('.glass-card')?.parentElement;
            if (container) {
                const wrapper = document.createElement('div');
                wrapper.className = 'col-span-full flex justify-end mb-1';
                wrapper.innerHTML = '<span id="esp32-status-badge" class="px-2.5 py-1 rounded-full text-xs font-bold tracking-wide"></span>';
                container.prepend(wrapper);
            }
        }
        return document.getElementById('esp32-status-badge');
    }

    function setEsp32Online() {
        const badge = getOrCreateEsp32Badge();
        if (badge) {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold tracking-wide bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
            badge.innerText = '🟢 ESP32 Online';
        }
    }

    function setEsp32Offline() {
        const badge = getOrCreateEsp32Badge();
        if (badge) {
            badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold tracking-wide bg-rose-500/20 text-rose-400 border border-rose-500/30 animate-pulse';
            badge.innerText = '🔴 ESP32 Offline / Terputus';
        }
        // Nol-kan semua nilai sensor
        if (document.getElementById('sensor-temp')) {
            document.getElementById('sensor-temp').innerText = '0.0°C';
            document.getElementById('sensor-hum').innerText = '0%';
            document.getElementById('sensor-volt').innerHTML = '0.0 <span class="text-xs text-slate-400 font-normal">V</span>';
            document.getElementById('sensor-curr').innerHTML = '0.00 <span class="text-xs text-slate-400 font-normal">A</span>';
            document.getElementById('sensor-power').innerHTML = '0.0 <span class="text-xs text-slate-400 font-normal">W</span>';
            document.getElementById('sensor-energy').innerText = '0.0000';
            const tempBadge = document.getElementById('temp-badge');
            if (tempBadge) {
                tempBadge.innerHTML = '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">Offline</span>';
            }
        }
    }

    function updateDashboard() {
        fetch('{{ route('dashboard.realtime') }}')
            .then(res => res.json())
            .then(data => {
                document.getElementById('last-sync-time').innerText = 'Sinkron: ' + new Date().toLocaleTimeString();

                // 1. Update 3 Slot
                for (let i = 1; i <= 3; i++) {
                    const slot = data.slots['slot' + i] || { status: 'STANDBY', active_duration: 0, started_at: 0 };
                    const status = (slot.status || 'STANDBY').toUpperCase();
                    const badge = document.getElementById('badge-slot-' + i);
                    const countdown = document.getElementById('countdown-slot-' + i);
                    const duration = document.getElementById('duration-slot-' + i);
                    const paid = document.getElementById('paid-slot-' + i);
                    const card = document.getElementById('card-slot-' + i);
                    const btnStop = document.getElementById('btn-stop-slot-' + i);

                    // Styling badge sesuai status
                    if (status === 'ACTIVE') {
                        badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold tracking-wide bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 animate-pulse';
                        badge.innerText = 'SEDANG AKTIF';
                        card.classList.add('border-emerald-500/40');
                        btnStop.disabled = false;

                        // Hitung Countdown
                        const startedAt = parseInt(slot.started_at) || 0;
                        const durationSec = parseInt(slot.active_duration) || 0;
                        const nowSec = Math.floor(Date.now() / 1000);
                        const elapsed = nowSec - startedAt;
                        const remaining = Math.max(0, durationSec - elapsed);

                        const m = Math.floor(remaining / 60).toString().padStart(2, '0');
                        const s = (remaining % 60).toString().padStart(2, '0');
                        countdown.innerText = `${m}:${s}`;
                        countdown.className = 'text-3xl font-mono font-extrabold tracking-tight text-emerald-400';

                        duration.innerText = Math.round(durationSec / 60) + ' Menit';
                        paid.innerText = 'Rp ' + (slot.nominal_paid ? Number(slot.nominal_paid).toLocaleString('id-ID') : '1.000');
                    } else if (status === 'WAITING_PAYMENT') {
                        badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold tracking-wide bg-amber-500/20 text-amber-400 border border-amber-500/30 animate-pulse';
                        badge.innerText = 'MENUNGGU BAYAR';
                        card.classList.remove('border-emerald-500/40');
                        btnStop.disabled = false;
                        countdown.innerText = 'SCAN QR';
                        countdown.className = 'text-2xl font-mono font-bold tracking-tight text-amber-400';
                        duration.innerText = 'Menunggu';
                        paid.innerText = 'Menunggu';
                    } else {
                        badge.className = 'px-2.5 py-1 rounded-full text-xs font-bold tracking-wide bg-slate-800 text-slate-400 border border-slate-700';
                        badge.innerText = 'STANDBY';
                        card.classList.remove('border-emerald-500/40');
                        btnStop.disabled = true;
                        countdown.innerText = 'SIAP';
                        countdown.className = 'text-3xl font-mono font-extrabold tracking-tight text-slate-500';
                        duration.innerText = '-';
                        paid.innerText = '-';
                    }
                }

                // 2. Update Sensor Fisik (dengan Deteksi Offline ESP32)
                if (data.sensors) {
                    const incomingUpdatedAt = parseInt(data.sensors.updated_at) || 0;

                    // Cek apakah ada data baru dari ESP32 (updated_at berubah atau lebih baru)
                    if (incomingUpdatedAt > lastSensorUpdatedAt) {
                        lastSensorUpdatedAt = incomingUpdatedAt;
                    }

                    // Hitung selisih waktu sejak update terakhir
                    const nowUnix = Math.floor(Date.now() / 1000);
                    const secondsSinceUpdate = (lastSensorUpdatedAt > 0) ? (nowUnix - lastSensorUpdatedAt) : 9999;

                    if (lastSensorUpdatedAt === 0 || secondsSinceUpdate > 10) {
                        // ESP32 offline atau belum pernah kirim data
                        setEsp32Offline();
                    } else {
                        // ESP32 online — update semua nilai sensor normal
                        setEsp32Online();

                        if (document.getElementById('sensor-temp')) {
                            const temp = parseFloat(data.sensors.temperature || 0);
                            const hum = parseFloat(data.sensors.humidity || 0);

                            document.getElementById('sensor-temp').innerText = temp.toFixed(1) + '°C';
                            document.getElementById('sensor-hum').innerText = Math.round(hum) + '%';
                            document.getElementById('sensor-volt').innerHTML = parseFloat(data.sensors.voltage || 0).toFixed(1) + ' <span class="text-xs text-slate-400 font-normal">V</span>';
                            document.getElementById('sensor-curr').innerHTML = parseFloat(data.sensors.current || 0).toFixed(2) + ' <span class="text-xs text-slate-400 font-normal">A</span>';
                            document.getElementById('sensor-power').innerHTML = parseFloat(data.sensors.power || 0).toFixed(1) + ' <span class="text-xs text-slate-400 font-normal">W</span>';
                            document.getElementById('sensor-energy').innerText = parseFloat(data.sensors.energy || 0).toFixed(4);

                            const tempBadge = document.getElementById('temp-badge');
                            if (temp >= OVERHEAT_LIMIT && OVERHEAT_LIMIT > 0) {
                                tempBadge.innerHTML = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-400 animate-bounce">OVERHEAT!</span>';
                            } else if (temp > 0) {
                                tempBadge.innerHTML = '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">Normal</span>';
                            } else {
                                tempBadge.innerHTML = '<span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400">Standby</span>';
                            }
                        }
                    }
                }
            })
            .catch(err => {
                console.warn('Realtime sync pause:', err);
                document.getElementById('last-sync-time').innerText = 'Koneksi terputus...';
            });
    }

    // Jalankan segera saat halaman selesai dimuat, lalu ulang setiap 2 detik
    document.addEventListener('DOMContentLoaded', () => {
        updateDashboard();
        setInterval(updateDashboard, 2000);
    });
</script>
@endpush

