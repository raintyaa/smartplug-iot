<!DOCTYPE html>
<html lang="id" class="h-full bg-[#0A0A0D] text-neutral-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') | Smart Plug System</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        base: '#0A0A0D',
                        surface: '#131318',
                        surface2: '#181820',
                        hairline: '#282832',
                        mint: '#05DF72',
                        amber: '#F59E0B',
                        crimson: '#EF4444',
                        brand: {
                            500: '#05DF72',
                            600: '#00C864',
                            700: '#00A854',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0D; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .surface-card {
            background-color: #131318;
            border: 1px solid #282832;
        }
        /* Harden: hormati pengguna sensitif gerak — matikan pulse/ping, pertahankan makna via warna + teks */
        @media (prefers-reduced-motion: reduce) {
            .animate-pulse, .animate-ping, .pulse-amber {
                animation: none !important;
            }
            *, *::before, *::after {
                scroll-behavior: auto !important;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="h-full flex overflow-hidden bg-base text-neutral-200 antialiased selection:bg-mint/30">

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
    <main class="flex-1 flex flex-col overflow-hidden bg-base">
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

                <!-- Status Koneksi Firebase -->
                <div id="connection-status" class="inline-flex items-center font-mono text-xs font-medium px-2.5 sm:px-3 py-1 rounded-md bg-mint/10 text-mint" title="Status koneksi Firebase Realtime Database">
                    <span aria-hidden="true">●</span><span class="hidden sm:inline ml-1.5">Firebase</span><span class="sr-only">Firebase terhubung</span>
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
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-amber w-32">Tanpa Beban</dt><dd class="text-neutral-300">Relay aktif namun arus &lt; 0,03 A / daya &lt; 2,0 W — charger belum dicolok atau sudah dicabut.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-crimson w-32">OVERHEAT!</dt><dd class="text-neutral-300">Suhu box &ge; 42,0 °C. Pemutus darurat aktif, periksa ventilasi sebelum menyalakan lagi.</dd></div>
                <div class="flex gap-3"><dt class="flex-shrink-0 font-mono font-semibold text-neutral-200 w-32">Agregat</dt><dd class="text-neutral-300">1 sensor PZEM di kabel fasa utama. Saat &gt; 1 slot aktif, angka Watt adalah total gabungan, bukan per-slot.</dd></div>
            </dl>
        </div>
    </div>

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

            function updateGlobalSync() {
                if (window.hasCustomDashboardLoop) return;

                fetch("{{ route('dashboard.realtime') }}")
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
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

                            if (lastGlobalSensorUpdate === 0 || diff > 25) {
                                setEsp32BadgeOffline();
                            } else {
                                setEsp32BadgeOnline();
                            }

                            var elVolt = document.getElementById('sensor-page-volt');
                            if (elVolt) elVolt.innerHTML = parseFloat(data.sensors.voltage || 0).toFixed(1) + ' <span class="text-sm font-normal text-neutral-400">V</span>';

                            var elCurr = document.getElementById('sensor-page-curr');
                            if (elCurr) elCurr.innerHTML = parseFloat(data.sensors.current || 0).toFixed(2) + ' <span class="text-sm font-normal text-neutral-400">A</span>';

                            var elPower = document.getElementById('sensor-page-power');
                            if (elPower) elPower.innerHTML = parseFloat(data.sensors.power || 0).toFixed(1) + ' <span class="text-sm font-normal text-neutral-400">W</span>';

                            var elTemp = document.getElementById('sensor-page-temp');
                            if (elTemp) elTemp.innerHTML = parseFloat(data.sensors.temperature || 0).toFixed(1) + '<span class="text-sm font-normal text-neutral-400">°C</span>';

                            var elHumid = document.getElementById('sensor-page-humid');
                            if (elHumid) elHumid.innerHTML = parseFloat(data.sensors.humidity || 0).toFixed(0) + '<span class="text-sm font-normal text-neutral-400">%</span>';
                        }
                    })
                    .catch(function(err) {
                        var syncEl = document.getElementById('last-sync-time');
                        if (syncEl) syncEl.innerText = 'Koneksi terputus...';
                    });
            }

            document.addEventListener('DOMContentLoaded', function() {
                if (!window.hasCustomDashboardLoop) {
                    updateGlobalSync();
                    globalSyncTimer = setInterval(updateGlobalSync, 3000);
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

            window.RanovaConfirm = { open: openConfirm, close: closeConfirm };

            document.addEventListener('submit', function (e) {
                var form = e.target && e.target.closest ? e.target.closest('form.js-confirm-form') : null;
                if (!form) return;
                if (form.dataset.confirmed === '1') { form.dataset.confirmed = ''; return; }
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
                var legend = document.getElementById('ranova-legend-modal');
                if (e.target.closest && e.target.closest('#legend-open-btn') && legend) {
                    lastFocused = document.activeElement;
                    legend.classList.remove('hidden'); legend.classList.add('flex');
                    var c = legend.querySelector('[data-legend-close]'); if (c) c.focus();
                }
                if (legend && (e.target.closest && (e.target.closest('[data-legend-close]')))) { legend.classList.add('hidden'); legend.classList.remove('flex'); if (lastFocused && lastFocused.focus) lastFocused.focus(); }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                els();
                if (modal && !modal.classList.contains('hidden')) closeConfirm();
                var legend = document.getElementById('ranova-legend-modal');
                if (legend && !legend.classList.contains('hidden')) { legend.classList.add('hidden'); legend.classList.remove('flex'); }
            });

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
