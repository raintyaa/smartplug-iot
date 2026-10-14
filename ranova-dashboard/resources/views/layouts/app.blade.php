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
    </style>
    @stack('styles')
</head>
<body class="h-full flex overflow-hidden bg-base text-neutral-200 antialiased selection:bg-mint/30">

    <!-- Sidebar Navigation -->
    <aside id="app-sidebar" class="w-64 bg-surface border-r border-hairline flex flex-col justify-between flex-shrink-0 transition-all duration-300 ease-in-out">
        <div>
            <!-- Brand Logo -->
            <div class="h-16 flex items-center px-6 border-b border-hairline space-x-3">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-9 h-9 rounded-lg border border-hairline object-contain bg-base">
                <div>
                    <span class="font-bold text-base tracking-tight text-white">SMART PLUG</span>
                    <span class="block text-xs tracking-wider text-neutral-300 font-medium">Operator Console</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-3 space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('dashboard') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-gauge-high w-4 text-center"></i>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('transactions.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('transactions.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-receipt w-4 text-center"></i>
                    <span>Riwayat Transaksi</span>
                </a>

                <a href="{{ route('reports.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('reports.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-chart-line w-4 text-center"></i>
                    <span>Laporan & Finansial</span>
                </a>

                <a href="{{ route('sensors.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('sensors.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-microchip w-4 text-center"></i>
                    <span>Telemetri Sensor</span>
                </a>

                <a href="{{ route('settings.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('settings.*') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-sliders w-4 text-center"></i>
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
                        <p class="text-xs text-neutral-300 truncate">Online via Local</p>
                    </div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center space-x-2 px-3 py-2 rounded-lg text-xs font-medium text-crimson bg-crimson/10 hover:bg-crimson/20 border border-crimson/30 transition">
                    <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                    <span>Keluar (Logout)</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col overflow-hidden bg-base">
        <!-- Topbar Header -->
        <header class="h-16 border-b border-hairline bg-surface px-8 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center space-x-3">
                <button id="sidebar-toggle" class="p-2 mr-2 rounded-lg text-neutral-400 hover:text-white hover:bg-surface2 border border-transparent hover:border-hairline transition focus:outline-none" title="Toggle Sidebar">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h1 class="text-2xl font-bold text-white tracking-tight">@yield('page_title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center space-x-3">
                <!-- Sync Clock -->
                <span id="last-sync-time" class="font-mono text-xs text-neutral-300 px-2.5 py-1.5 rounded-lg bg-surface2 border border-hairline">
                    Sinkron: {{ date('H:i:s') }}
                </span>

                <!-- Status ESP32 Badge (Dynamic JS) -->
                <span id="esp32-badge" class="font-mono text-xs font-medium px-3 py-1.5 rounded-lg bg-surface2 border border-hairline text-neutral-300">
                    ESP32
                </span>

                <!-- Status Koneksi Firebase -->
                <div id="connection-status" class="font-mono text-xs font-medium px-3 py-1.5 rounded-lg bg-mint/10 border border-mint/25 text-mint">
                    Firebase
                </div>

                <!-- Emergency Shutdown Button -->
                <form action="{{ route('dashboard.emergency_stop') }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin mematikan SELURUH relay secara darurat?')">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3.5 py-1.5 rounded-lg font-mono text-xs font-semibold uppercase tracking-wider text-crimson bg-crimson/10 border border-crimson/30 hover:bg-crimson/20 transition">
                        Emergency Shutdown
                    </button>
                </form>
            </div>
        </header>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mx-8 mt-4 p-3.5 rounded-lg bg-mint/10 border border-mint/30 text-mint text-xs flex items-center space-x-2">
                <i class="fa-solid fa-circle-check text-mint"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-8 mt-4 p-3.5 rounded-lg bg-amber/10 border border-amber/30 text-amber text-xs flex items-center space-x-2">
                <i class="fa-solid fa-triangle-exclamation text-amber"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-8 mt-4 p-3.5 rounded-lg bg-crimson/10 border border-crimson/30 text-crimson text-xs flex items-center space-x-2">
                <i class="fa-solid fa-circle-xmark text-crimson"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Scrollable Page Body -->
        <div class="flex-1 overflow-y-auto p-8">
            @yield('content')
        </div>
    </main>

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
                    badge.className = 'font-mono text-xs font-medium px-3 py-1.5 rounded-lg bg-mint/10 border border-mint/25 text-mint';
                    badge.innerText = 'ESP32';
                }
            }

            function setEsp32BadgeOffline() {
                var badge = document.getElementById('esp32-badge');
                if (badge) {
                    badge.className = 'font-mono text-xs font-medium px-3 py-1.5 rounded-lg bg-surface2 border border-hairline text-neutral-400';
                    badge.innerText = 'ESP32 Offline';
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

    @stack('scripts')
</body>
</html>
