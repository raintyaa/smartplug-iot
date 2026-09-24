<!DOCTYPE html>
<html lang="id" class="h-full bg-[#0A0A0D] text-neutral-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — RANOVA Smart Plug</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
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
    <aside class="w-64 bg-surface border-r border-hairline flex flex-col justify-between flex-shrink-0">
        <div>
            <!-- Brand Logo -->
            <div class="h-16 flex items-center px-6 border-b border-hairline space-x-3">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-9 h-9 rounded-lg border border-hairline object-contain bg-base">
                <div>
                    <span class="font-bold text-base tracking-tight text-white">RANOVA</span>
                    <span class="block text-[10px] tracking-wider text-neutral-400 font-medium">Smart Plug IoT Station</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-3 space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-xs font-medium transition {{ request()->routeIs('dashboard') ? 'bg-mint/10 text-mint border border-mint/25' : 'text-neutral-400 hover:text-white hover:bg-surface2' }}">
                    <i class="fa-solid fa-gauge-high w-4 text-center"></i>
                    <span>Dashboard Realtime</span>
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
                    <span>Pengaturan Sistem</span>
                </a>
            </nav>
        </div>

        <!-- Operator Profile & Logout -->
        <div class="p-3 border-t border-hairline">
            <div class="flex items-center justify-between mb-3 px-2">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full bg-surface2 border border-hairline flex items-center justify-center text-xs font-bold text-mint">
                        OP
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-semibold text-white truncate">{{ Auth::user()->name ?? 'Operator' }}</p>
                        <p class="text-[11px] text-neutral-400 truncate">Online via Local</p>
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
                <h1 class="text-base font-semibold text-white tracking-tight">@yield('page_title', 'Dashboard')</h1>
            </div>

            <div class="flex items-center space-x-3">
                <!-- Sync Clock -->
                <span id="last-sync-time" class="font-mono text-xs text-neutral-400 px-2.5 py-1.5 rounded-lg bg-base border border-hairline">
                    Sinkron: --:--:--
                </span>

                <!-- Status ESP32 Badge (Dynamic JS) -->
                <span id="esp32-badge" class="text-xs font-medium px-3 py-1.5 rounded-lg bg-surface2 border border-hairline text-neutral-400">
                    ESP32
                </span>

                <!-- Status Koneksi Firebase -->
                <div id="connection-status" class="text-xs font-medium px-3 py-1.5 rounded-lg bg-mint/10 border border-mint/25 text-mint">
                    Firebase
                </div>

                <!-- Emergency Shutdown Button -->
                <form action="{{ route('dashboard.emergency_stop') }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin mematikan SELURUH relay secara darurat?')">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-3.5 py-1.5 rounded-lg text-xs font-semibold uppercase tracking-wider text-crimson bg-crimson/10 border border-crimson/30 hover:bg-crimson/20 transition">
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

    @stack('scripts')
</body>
</html>
