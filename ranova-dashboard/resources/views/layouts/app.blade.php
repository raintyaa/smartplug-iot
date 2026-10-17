<!DOCTYPE html>
<html lang="id" class="h-full bg-[#0A0A0D] text-neutral-200">
<head>
    <title>@yield('title', 'Dashboard') | Smart Plug System</title>
    @include('layouts.head')
    <!-- Chart.js (deferred for non-blocking first paint) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    @stack('styles')
</head>
<body class="h-full flex overflow-hidden bg-base text-neutral-200 antialiased selection:bg-mint/30">

    <!-- Skip-link to main content for keyboard / screen reader accessibility (WCAG 2.4.1) -->
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[999] focus:px-4 focus:py-2.5 focus:rounded-lg focus:bg-surface focus:border focus:border-mint focus:text-mint focus:font-semibold focus:text-xs focus:shadow-xl focus:outline-none focus:ring-2 focus:ring-mint">
        Lewati ke konten utama
    </a>

    <!-- Sidebar Navigation -->
    <aside id="app-sidebar" class="w-64 bg-surface border-r border-hairline flex flex-col justify-between flex-shrink-0 transition-all duration-300 ease-in-out">
        <div>
            <!-- Brand Logo -->
            <div class="h-16 flex items-center px-6 border-b border-hairline space-x-3">
                <img src="{{ asset('images/logo.png') }}" alt="Logo RANOVA Smart Plug" class="w-9 h-9 rounded-lg border border-hairline object-contain bg-base">
                <div>
                    <span class="font-bold text-base tracking-tight text-white">SMART PLUG</span>
                    <span class="block text-xs tracking-wider text-neutral-300 font-medium">Operator Console</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-3 space-y-1" aria-label="Navigasi operator">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3.5 py-2.5 min-h-[44px] rounded-lg text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base {{ request()->routeIs('dashboard') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-gauge-high w-4 text-center" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('transactions.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 min-h-[44px] rounded-lg text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base {{ request()->routeIs('transactions.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-receipt w-4 text-center" aria-hidden="true"></i>
                    <span>Riwayat Transaksi</span>
                </a>

                <a href="{{ route('reports.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 min-h-[44px] rounded-lg text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base {{ request()->routeIs('reports.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-chart-line w-4 text-center" aria-hidden="true"></i>
                    <span>Laporan & Finansial</span>
                </a>

                <a href="{{ route('sensors.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 min-h-[44px] rounded-lg text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base {{ request()->routeIs('sensors.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-microchip w-4 text-center" aria-hidden="true"></i>
                    <span>Telemetri Sensor</span>
                </a>

                <a href="{{ route('settings.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 min-h-[44px] rounded-lg text-xs font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base {{ request()->routeIs('settings.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-sliders w-4 text-center" aria-hidden="true"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <!-- Operator Profile & Logout -->
        <div class="p-3 border-t border-hairline">
            <div class="flex items-center justify-between mb-3 px-2">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full bg-surface2 border border-hairline flex items-center justify-center text-xs font-bold text-mint font-mono">
                        OP
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name ?? 'Operator' }}</p>
                        <p class="text-xs text-neutral-300 truncate">Operator lokal</p>
                    </div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST" class="js-confirm-form" data-confirm-title="Keluar dari konsol?" data-confirm-message="Sesi operator akan diakhiri. Monitoring realtime berhenti di perangkat ini." data-confirm-text="Ya, Keluar" data-confirm-tone="neutral">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center space-x-2 px-3 py-2 min-h-[44px] rounded-lg text-xs font-medium text-crimson bg-crimson/10 hover:bg-crimson/20 border border-crimson/30 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs" aria-hidden="true"></i>
                    <span>Keluar (Logout)</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main id="main-content" tabindex="-1" class="flex-1 flex flex-col overflow-hidden bg-base outline-none">
        <!-- Topbar Header -->
        <header class="min-h-[4rem] border-b border-hairline bg-surface px-4 sm:px-8 py-2 flex items-center justify-between gap-2 flex-shrink-0 flex-wrap">
            <div class="flex items-center space-x-2 sm:space-x-3 min-w-0">
                <button id="sidebar-toggle" class="p-2.5 mr-1 sm:mr-2 rounded-lg text-neutral-400 hover:text-white hover:bg-surface2 border border-transparent hover:border-hairline transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base min-w-[44px] min-h-[44px] flex items-center justify-center" title="Toggle Sidebar" aria-label="Tampilkan atau sembunyikan navigasi" aria-expanded="true">
                    <i class="fa-solid fa-bars text-sm" aria-hidden="true"></i>
                </button>
                <h1 class="text-lg sm:text-2xl font-bold text-white tracking-tight truncate">@yield('page_title', 'Dashboard')</h1>
                <button id="legend-open-btn" type="button" class="ml-1 inline-flex items-center justify-center min-w-[44px] min-h-[44px] sm:w-auto sm:px-2.5 sm:py-1.5 rounded-lg text-xs font-mono text-neutral-300 bg-surface2 border border-hairline hover:text-white hover:border-mint/40 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base" title="Panduan status operator" aria-label="Buka panduan status operator" aria-haspopup="dialog">
                    <i class="fa-solid fa-circle-question" aria-hidden="true"></i>
                    <span class="hidden sm:inline ml-1.5">Panduan</span>
                </button>
            </div>

            <div class="flex items-center gap-2 sm:space-x-3 flex-shrink-0">
                <!-- Sync Clock -->
                <span id="last-sync-time" class="hidden md:inline-flex items-center font-mono text-xs text-neutral-300 px-2.5 py-1 rounded-md bg-surface2/80" title="Waktu sinkronisasi terakhir dengan server">
                    Sinkron: {{ date('H:i:s') }}
                </span>

                <!-- Status ESP32 Badge (Dynamic JS) -->
                <span id="esp32-badge" class="inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-300" title="Status koneksi mikrokontroler ESP32">
                    <span class="sm:hidden" aria-hidden="true">●</span><span class="hidden sm:inline">ESP32</span><span class="sm:hidden sr-only">ESP32</span>
                </span>

                <!-- Status Koneksi MQTT Broker (Dynamic JS) -->
                <div id="mqtt-badge" class="inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-400" title="Status koneksi MQTT Realtime Broker (EMQX Cloud)">
                    <span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">MQTT</span><span class="sr-only">MQTT</span>
                </div>

                <!-- Emergency Shutdown Button -->
                <form action="{{ route('dashboard.emergency_stop') }}" method="POST" class="js-confirm-form" data-confirm-title="Emergency Shutdown?" data-confirm-message="SELURUH relay (Slot 1–3) akan dimatikan seketika. Sesi sewa aktif ikut terhenti. Lanjutkan?" data-confirm-text="Ya, Matikan Semua" data-confirm-tone="crimson">
                    @csrf
                    <button type="submit" aria-label="Matikan semua relay darurat" class="inline-flex items-center justify-center min-w-[44px] min-h-[44px] px-3 sm:px-3.5 py-1.5 rounded-lg font-mono text-xs font-semibold uppercase tracking-wider text-crimson bg-crimson/10 border border-crimson/30 hover:bg-crimson/20 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                        <i class="fa-solid fa-triangle-exclamation sm:mr-1.5" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Emergency Shutdown</span>
                        <span class="sm:hidden">STOP</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mx-4 sm:mx-8 mt-4 p-3.5 rounded-lg bg-mint/10 border border-mint/30 text-mint text-xs flex items-center space-x-2" role="status">
                <i class="fa-solid fa-circle-check text-mint" aria-hidden="true"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-4 sm:mx-8 mt-4 p-3.5 rounded-lg bg-amber/10 border border-amber/30 text-amber text-xs flex items-center space-x-2" role="status">
                <i class="fa-solid fa-triangle-exclamation text-amber" aria-hidden="true"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-4 sm:mx-8 mt-4 p-3.5 rounded-lg bg-crimson/10 border border-crimson/30 text-crimson text-xs flex items-center space-x-2" role="alert">
                <i class="fa-solid fa-circle-xmark text-crimson" aria-hidden="true"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Scrollable Page Body -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-8">
            @yield('content')
        </div>
    </main>

    <!-- Global Accessible Confirm Modal -->
    <div id="ranova-confirm-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ranova-confirm-title" aria-describedby="ranova-confirm-message">
        <div class="absolute inset-0 bg-black/70" data-confirm-close></div>
        <div class="relative w-full max-w-md rounded-xl bg-surface border border-hairline p-6 shadow-xl">
            <div class="flex items-start gap-3">
                <div id="ranova-confirm-icon" class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 bg-crimson/15 border border-crimson/30 text-crimson">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                </div>
                <div class="min-w-0">
                    <h2 id="ranova-confirm-title" class="text-base font-semibold text-white tracking-tight">Konfirmasi aksi?</h2>
                    <p id="ranova-confirm-message" class="mt-1.5 text-xs text-neutral-300 leading-relaxed">Apakah Anda yakin?</p>
                </div>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-2">
                <button type="button" id="ranova-confirm-cancel" class="w-full min-h-[44px] px-4 py-2.5 rounded-lg text-xs font-semibold bg-surface2 border border-hairline text-neutral-200 hover:bg-white/[0.05] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                    Batal
                </button>
                <button type="button" id="ranova-confirm-ok" class="w-full min-h-[44px] px-4 py-2.5 rounded-lg text-xs font-semibold bg-crimson/15 border border-crimson/40 text-crimson hover:bg-crimson/25 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson focus-visible:ring-offset-2 focus-visible:ring-offset-base">
                    Konfirmasi
                </button>
            </div>
        </div>
    </div>

    <!-- Operator Legend / Panduan Status Modal -->
    <div id="ranova-legend-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="ranova-legend-title">
        <div class="absolute inset-0 bg-black/70" data-legend-close></div>
        <div class="relative w-full max-w-lg rounded-xl bg-surface border border-hairline p-6 shadow-xl max-h-[85vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 id="ranova-legend-title" class="text-base font-semibold text-white tracking-tight">Panduan Status Operator</h2>
                    <p class="mt-1 text-xs text-neutral-300">Arti badge dan indikator di dashboard dalam 10 detik.</p>
                </div>
                <button type="button" data-legend-close aria-label="Tutup panduan" class="w-[44px] h-[44px] rounded-lg text-neutral-300 bg-surface2 border border-hairline hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base flex-shrink-0">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
            <dl class="mt-4 space-y-3 text-xs leading-relaxed">
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-neutral-200 w-32">SIAP</dt><dd class="text-neutral-300">Stop kontak standby, siap disewa via tombol fisik box.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-amber w-32">MENUNGGU BAYAR</dt><dd class="text-neutral-300">Tombol ditekan, QRIS sedang menunggu pembayaran pelanggan.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-mint w-32">AKTIF</dt><dd class="text-neutral-300">Relay ON, countdown sewa berjalan normal.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-amber w-32">MANUAL ON</dt><dd class="text-neutral-300">Dinyalakan langsung admin tanpa batas waktu (Bypass, Rp 0). Wajib dimatikan manual.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-amber w-32">DIJEDA (SUHU)</dt><dd class="text-neutral-300">Proteksi termal aktif (suhu &ge; 42°C). Relay OFF dan sisa durasi dibekukan hingga suhu &lt; 37°C.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-neutral-400 w-32">OFFLINE</dt><dd class="text-neutral-300">Steker atau unit ESP32 tidak terhubung listrik / jaringan.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-amber w-32">Tanpa Beban</dt><dd class="text-neutral-300">Relay aktif namun arus &lt; 0,03 A / daya &lt; 2,0 W — charger belum dicolok atau sudah dicabut.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-crimson w-32">OVERHEAT!</dt><dd class="text-neutral-300">Suhu box &ge; 42,0 °C. Pemutus darurat aktif, periksa ventilasi sebelum menyalakan lagi.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-neutral-200 w-32">Agregat</dt><dd class="text-neutral-300">1 sensor PZEM di kabel fasa utama. Saat &gt; 1 slot aktif, angka Watt adalah total gabungan, bukan per-slot.</dd></div>
            </dl>
        </div>
    </div>

    <!-- Global Floating Toast Notification Container -->
    <div id="ranova-toast-container" class="fixed bottom-5 right-5 z-[120] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0" aria-live="polite" aria-atomic="true"></div>

    <script>
        (function () {
            var STORAGE_KEY = 'sidebar_collapsed';
            var sidebar = document.getElementById('app-sidebar');
            var toggleBtn = document.getElementById('sidebar-toggle');

            function applySidebarState() {
                if (!sidebar) return;
                var collapsed = false;
                try {
                    collapsed = localStorage.getItem(STORAGE_KEY) === '1';
                } catch (e) {
                    collapsed = false;
                }
                sidebar.classList.toggle('-ml-64', collapsed);
            }

            // Apply immediately (before paint) to prevent visual flickering on navigation.
            applySidebarState();

            document.addEventListener('DOMContentLoaded', function () {
                sidebar = document.getElementById('app-sidebar');
                toggleBtn = document.getElementById('sidebar-toggle');
                applySidebarState();

                if (toggleBtn && sidebar) {
                    toggleBtn.addEventListener('click', function () {
                        var isCollapsed = sidebar.classList.toggle('-ml-64');
                        try {
                            localStorage.setItem(STORAGE_KEY, isCollapsed ? '1' : '0');
                        } catch (e) {
                            /* storage unavailable: keep UI-only toggle */
                        }
                    });
                }
            });
        })();
    </script>

    <!-- Global Sync Heartbeat across All Tabs -->
    <script>
        (function() {
            var globalSyncTimer = null;
            var lastGlobalSensorUpdate = 0;
            var isGlobalPolling = false;
            var globalBackoffMs = 3000;

            function setEsp32BadgeOnline() {
                var badge = document.getElementById('esp32-badge');
                if (badge) {
                    badge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-mint/10 text-mint';
                    badge.setAttribute('title', 'ESP32 online — telemetri realtime');
                    badge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">ESP32</span><span class="sr-only">ESP32 online</span>';
                }
            }

            function setEsp32BadgeOffline() {
                var badge = document.getElementById('esp32-badge');
                if (badge) {
                    badge.className = 'inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-surface2 text-neutral-400';
                    badge.setAttribute('title', 'ESP32 offline — data sensor basi');
                    badge.innerHTML = '<span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">ESP32 Offline</span><span class="sr-only">ESP32 offline</span>';
                }
            }

            function scheduleNextGlobalPoll(delay) {
                if (globalSyncTimer) clearTimeout(globalSyncTimer);
                globalSyncTimer = setTimeout(updateGlobalSync, delay);
            }

            function updateGlobalSync() {
                if (window.hasCustomDashboardLoop) return;
                if (isGlobalPolling) {
                    scheduleNextGlobalPoll(globalBackoffMs);
                    return;
                }
                isGlobalPolling = true;

                var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var timeoutId = controller ? setTimeout(function() { controller.abort(); }, 8000) : null;
                var fetchOpts = controller ? { signal: controller.signal } : {};

                fetch("{{ route('dashboard.realtime') }}", fetchOpts)
                    .then(function(res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    })
                    .then(function(data) {
                        if (timeoutId) clearTimeout(timeoutId);
                        isGlobalPolling = false;
                        globalBackoffMs = 3000; // Reset backoff upon successful request

                        var now = new Date();
                        var timeStr = now.toTimeString().split(' ')[0];
                        var syncEl = document.getElementById('last-sync-time');
                        if (syncEl) syncEl.innerText = 'Sinkron: ' + timeStr;

                        if (data && data.sensors) {
                            var incomingUpdate = parseInt(data.sensors.updated_at) || 0;
                            if (incomingUpdate > lastGlobalSensorUpdate) {
                                lastGlobalSensorUpdate = incomingUpdate;
                            }
                            var nowUnix = Math.floor(Date.now() / 1000);
                            var diff = (lastGlobalSensorUpdate > 0) ? (nowUnix - lastGlobalSensorUpdate) : 9999;

                            var elVolt = document.getElementById('sensor-page-volt');
                            var elCurr = document.getElementById('sensor-page-curr');
                            var elPower = document.getElementById('sensor-page-power');
                            var elTemp = document.getElementById('sensor-page-temp');
                            var elHumid = document.getElementById('sensor-page-humid');

                            var elVoltBadge = document.getElementById('sensor-page-volt-badge');
                            var elCurrBadge = document.getElementById('sensor-page-curr-badge');
                            var elPowerBadge = document.getElementById('sensor-page-power-badge');
                            var elTempBadge = document.getElementById('sensor-page-temp-badge');
                            var elHumidBadge = document.getElementById('sensor-page-humid-badge');

                            if (lastGlobalSensorUpdate === 0 || diff > 25) {
                                setEsp32BadgeOffline();

                                // Samakan dengan dashboard: saat ESP32 offline, nol-kan seluruh telemetri sensor
                                if (elVolt) elVolt.innerHTML = '0.0 <span class="text-sm font-normal text-neutral-400">V</span>';
                                if (elCurr) elCurr.innerHTML = '0.00 <span class="text-sm font-normal text-neutral-400">A</span>';
                                if (elPower) elPower.innerHTML = '0.0 <span class="text-sm font-normal text-neutral-400">W</span>';
                                if (elTemp) elTemp.innerHTML = '0.0<span class="text-sm font-normal text-neutral-400">°C</span>';
                                if (elHumid) elHumid.innerHTML = '0<span class="text-sm font-normal text-neutral-400">%</span>';

                                if (elVoltBadge) elVoltBadge.innerText = 'ESP32 Offline';
                                if (elCurrBadge) elCurrBadge.innerText = 'ESP32 Offline';
                                if (elPowerBadge) elPowerBadge.innerText = 'ESP32 Offline';
                                if (elTempBadge) elTempBadge.innerText = 'ESP32 Offline';
                                if (elHumidBadge) elHumidBadge.innerText = 'ESP32 Offline';
                            } else {
                                setEsp32BadgeOnline();

                                if (elVolt) elVolt.innerHTML = parseFloat(data.sensors.voltage || 0).toFixed(1) + ' <span class="text-sm font-normal text-neutral-400">V</span>';
                                if (elCurr) elCurr.innerHTML = parseFloat(data.sensors.current || 0).toFixed(2) + ' <span class="text-sm font-normal text-neutral-400">A</span>';
                                if (elPower) elPower.innerHTML = parseFloat(data.sensors.power || 0).toFixed(1) + ' <span class="text-sm font-normal text-neutral-400">W</span>';
                                if (elTemp) elTemp.innerHTML = parseFloat(data.sensors.temperature || 0).toFixed(1) + '<span class="text-sm font-normal text-neutral-400">°C</span>';
                                if (elHumid) elHumid.innerHTML = parseFloat(data.sensors.humidity || 0).toFixed(0) + '<span class="text-sm font-normal text-neutral-400">%</span>';

                                if (elVoltBadge) elVoltBadge.innerText = 'AC 50Hz';
                                if (elCurrBadge) elCurrBadge.innerText = 'Donat CT';
                                if (elPowerBadge) elPowerBadge.innerText = 'Beban Realtime';
                                if (elTempBadge) elTempBadge.innerText = 'Sensor DHT22';
                                if (elHumidBadge) elHumidBadge.innerText = 'Udara Box';
                            }
                        }
                        scheduleNextGlobalPoll(globalBackoffMs);
                    })
                    .catch(function(err) {
                        if (timeoutId) clearTimeout(timeoutId);
                        isGlobalPolling = false;
                        // Exponential backoff 3s -> 6s -> 8s max
                        globalBackoffMs = Math.min(globalBackoffMs * 2, 8000);
                        var syncEl = document.getElementById('last-sync-time');
                        if (syncEl) syncEl.innerText = 'Koneksi terputus...';
                        scheduleNextGlobalPoll(globalBackoffMs);
                    });
            }

            document.addEventListener('DOMContentLoaded', function() {
                if (!window.hasCustomDashboardLoop) {
                    updateGlobalSync();
                }
            });
        })();
    </script>

    <!-- Global Confirm + Legend Modal Logic -->
    <script>
        (function () {
            var pendingForm = null;
            var lastFocused = null;
            var modal = null, titleEl = null, msgEl = null, okBtn = null, cancelBtn = null, iconBox = null;

            function els() {
                modal = document.getElementById('ranova-confirm-modal');
                titleEl = document.getElementById('ranova-confirm-title');
                msgEl = document.getElementById('ranova-confirm-message');
                okBtn = document.getElementById('ranova-confirm-ok');
                cancelBtn = document.getElementById('ranova-confirm-cancel');
                iconBox = document.getElementById('ranova-confirm-icon');
            }

            function toneClasses(tone) {
                if (tone === 'mint') return 'w-full min-h-[44px] px-4 py-2.5 rounded-lg text-xs font-semibold bg-mint/15 border border-mint/40 text-mint hover:bg-mint/25 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base';
                if (tone === 'neutral') return 'w-full min-h-[44px] px-4 py-2.5 rounded-lg text-xs font-semibold bg-surface2 border border-hairline text-neutral-200 hover:bg-white/[0.05] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base';
                return 'w-full min-h-[44px] px-4 py-2.5 rounded-lg text-xs font-semibold bg-crimson/15 border border-crimson/40 text-crimson hover:bg-crimson/25 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-crimson focus-visible:ring-offset-2 focus-visible:ring-offset-base';
            }

            function trapFocus(modalEl, e) {
                if (e.key !== 'Tab') return;
                var focusables = modalEl.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
                if (!focusables || focusables.length === 0) return;
                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                if (e.shiftKey) {
                    if (document.activeElement === first) {
                        e.preventDefault();
                        last.focus();
                    }
                } else {
                    if (document.activeElement === last) {
                        e.preventDefault();
                        first.focus();
                    }
                }
            }

            function openConfirm(opts) {
                els();
                if (!modal) { if (opts.form) opts.form.submit(); return; }
                pendingForm = opts.form || null;
                lastFocused = document.activeElement;
                titleEl.textContent = opts.title || 'Konfirmasi aksi?';
                msgEl.textContent = opts.message || 'Apakah Anda yakin?';
                okBtn.textContent = opts.confirmText || 'Konfirmasi';
                okBtn.className = toneClasses(opts.tone);
                iconBox.className = 'w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ' + (opts.tone === 'mint' ? 'bg-mint/15 border border-mint/30 text-mint' : (opts.tone === 'neutral' ? 'bg-surface2 border border-hairline text-neutral-200' : 'bg-crimson/15 border border-crimson/30 text-crimson'));
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                (opts.tone === 'crimson' ? okBtn : cancelBtn).focus();
            }

            function closeConfirm() {
                if (!modal) return;
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                pendingForm = null;
                if (lastFocused && lastFocused.focus) lastFocused.focus();
            }

            function openLegend() {
                var legend = document.getElementById('ranova-legend-modal');
                if (!legend) return;
                lastFocused = document.activeElement;
                legend.classList.remove('hidden');
                legend.classList.add('flex');
                document.body.classList.add('overflow-hidden');
                var c = legend.querySelector('[data-legend-close]');
                if (c) c.focus();
            }

            function closeLegend() {
                var legend = document.getElementById('ranova-legend-modal');
                if (!legend) return;
                legend.classList.add('hidden');
                legend.classList.remove('flex');
                document.body.classList.remove('overflow-hidden');
                if (lastFocused && lastFocused.focus) lastFocused.focus();
            }

            window.RanovaConfirm = { open: openConfirm, close: closeConfirm };

            document.addEventListener('submit', function (e) {
                var form = e.target && e.target.closest ? e.target.closest('form.js-confirm-form') : null;
                if (!form) return;
                if (form.dataset.confirmed === '1') {
                    setTimeout(function () { if (form) form.dataset.confirmed = ''; }, 500);
                    return;
                }
                e.preventDefault();
                openConfirm({
                    title: form.dataset.confirmTitle,
                    message: form.dataset.confirmMessage,
                    confirmText: form.dataset.confirmText,
                    tone: form.dataset.confirmTone || 'crimson',
                    form: form
                });
            }, true);

            document.addEventListener('click', function (e) {
                els();
                if (e.target.closest && e.target.closest('[data-confirm-close]')) closeConfirm();
                if (e.target.closest && e.target.closest('#ranova-confirm-cancel')) closeConfirm();
                if (e.target.closest && e.target.closest('#ranova-confirm-ok')) {
                    if (pendingForm) { var f = pendingForm; pendingForm = null; closeConfirm(); f.dataset.confirmed = '1'; f.requestSubmit ? f.requestSubmit() : f.submit(); }
                    else closeConfirm();
                }
                // Legend modal
                if (e.target.closest && e.target.closest('#legend-open-btn')) {
                    openLegend();
                }
                if (e.target.closest && e.target.closest('[data-legend-close]')) {
                    closeLegend();
                }
            });

            document.addEventListener('keydown', function (e) {
                els();
                var isConfirmOpen = modal && !modal.classList.contains('hidden');
                var legend = document.getElementById('ranova-legend-modal');
                var isLegendOpen = legend && !legend.classList.contains('hidden');

                if (e.key === 'Escape') {
                    if (isConfirmOpen) closeConfirm();
                    if (isLegendOpen) closeLegend();
                    return;
                }

                if (e.key === 'Tab') {
                    if (isConfirmOpen) {
                        trapFocus(modal, e);
                    } else if (isLegendOpen) {
                        trapFocus(legend, e);
                    }
                }
            });

            window.showToast = function (message, type, duration) {
                type = type || 'success';
                duration = duration || 3500;
                var container = document.getElementById('ranova-toast-container');
                if (!container) return;

                var toast = document.createElement('div');
                var iconClass = 'fa-solid fa-circle-check text-mint';
                var bgBorder = 'bg-surface border-mint/40 text-neutral-200';

                if (type === 'error' || type === 'crimson') {
                    iconClass = 'fa-solid fa-circle-xmark text-crimson';
                    bgBorder = 'bg-surface border-crimson/40 text-neutral-200';
                } else if (type === 'warning' || type === 'amber') {
                    iconClass = 'fa-solid fa-triangle-exclamation text-amber';
                    bgBorder = 'bg-surface border-amber/40 text-neutral-200';
                } else if (type === 'info' || type === 'sky') {
                    iconClass = 'fa-solid fa-circle-info text-sky-400';
                    bgBorder = 'bg-surface border-sky-400/40 text-neutral-200';
                }

                toast.className = 'pointer-events-auto flex items-start gap-3 p-3.5 rounded-xl border shadow-xl transition-all duration-300 transform translate-y-3 opacity-0 ' + bgBorder;
                toast.innerHTML = '<div class="mt-0.5 flex-shrink-0 text-sm"><i class="' + iconClass + '" aria-hidden="true"></i></div>' +
                    '<div class="flex-1 text-xs leading-relaxed font-medium">' + message + '</div>' +
                    '<button type="button" class="text-neutral-400 hover:text-neutral-200 text-xs ml-1 flex-shrink-0 transition-colors" aria-label="Tutup notifikasi"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>';

                var closeBtn = toast.querySelector('button');
                var timer = null;
                function dismiss() {
                    if (timer) clearTimeout(timer);
                    toast.classList.remove('opacity-100', 'translate-y-0');
                    toast.classList.add('opacity-0', 'translate-y-2');
                    setTimeout(function () {
                        if (toast.parentNode) toast.parentNode.removeChild(toast);
                    }, 300);
                }

                if (closeBtn) closeBtn.addEventListener('click', dismiss);

                container.appendChild(toast);

                requestAnimationFrame(function () {
                    toast.classList.remove('opacity-0', 'translate-y-3');
                    toast.classList.add('opacity-100', 'translate-y-0');
                });

                timer = setTimeout(dismiss, duration);
            };
            window.RanovaToast = { show: window.showToast };

            document.addEventListener('DOMContentLoaded', function () {
                var t = document.getElementById('sidebar-toggle');
                var sb = document.getElementById('app-sidebar');
                if (t && sb) t.setAttribute('aria-expanded', sb.classList.contains('-ml-64') ? 'false' : 'true');
                if (t) t.addEventListener('click', function () {
                    var collapsed = document.getElementById('app-sidebar').classList.contains('-ml-64');
                    t.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                });
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
