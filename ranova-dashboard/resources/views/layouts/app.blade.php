<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — RANOVA Smart Plug</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
    @stack('styles')
</head>
<body class="h-full flex overflow-hidden">

    <!-- Sidebar Navigation -->
    <aside class="w-64 bg-slate-950 border-r border-slate-800 flex flex-col justify-between flex-shrink-0">
        <div>
            <!-- Brand Logo -->
            <div class="h-16 flex items-center px-6 border-b border-slate-800 space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <i class="fa-solid fa-bolt text-slate-950 text-lg"></i>
                </div>
                <div>
                    <span class="font-bold text-lg tracking-tight bg-gradient-to-r from-emerald-400 to-teal-200 bg-clip-text text-transparent">RANOVA</span>
                    <span class="block text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Smart Plug IoT</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1">
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900' }}">
                    <i class="fa-solid fa-gauge-high w-5 text-center"></i>
                    <span>Dashboard Realtime</span>
                </a>

                <a href="{{ route('transactions.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('transactions.*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900' }}">
                    <i class="fa-solid fa-receipt w-5 text-center"></i>
                    <span>Riwayat Transaksi</span>
                </a>

                <a href="{{ route('reports.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('reports.*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900' }}">
                    <i class="fa-solid fa-chart-line w-5 text-center"></i>
                    <span>Laporan & Finansial</span>
                </a>

                <a href="{{ route('sensors.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('sensors.*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900' }}">
                    <i class="fa-solid fa-microchip w-5 text-center"></i>
                    <span>Telemetri Sensor</span>
                </a>

                <a href="{{ route('settings.index') }}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-lg text-sm font-medium transition {{ request()->routeIs('settings.*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-900' }}">
                    <i class="fa-solid fa-sliders w-5 text-center"></i>
                    <span>Pengaturan Sistem</span>
                </a>
            </nav>
        </div>

        <!-- Operator Profile & Logout -->
        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center justify-between mb-3 px-2">
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-emerald-400">
                        OP
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-xs font-semibold text-slate-200 truncate">{{ Auth::user()->name ?? 'Operator' }}</p>
                        <p class="text-[10px] text-slate-500 truncate">Online via Local</p>
                    </div>
                </div>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full flex items-center justify-center space-x-2 px-3 py-2 rounded-lg text-xs font-semibold text-rose-400 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>Keluar (Logout)</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 flex flex-col overflow-hidden bg-slate-900">
        <!-- Topbar Header -->
        <header class="h-16 border-b border-slate-800 bg-slate-950/60 px-8 flex items-center justify-between flex-shrink-0">
            <div class="flex items-center space-x-3">
                <h1 class="text-lg font-bold text-slate-100">@yield('page_title', 'Dashboard')</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Live Sync
                </span>
            </div>

            <div class="flex items-center space-x-4">
                <!-- Status Koneksi Firebase -->
                <div id="connection-status" class="flex items-center text-xs text-slate-400 space-x-1.5">
                    <i class="fa-solid fa-circle text-[8px] text-emerald-400"></i>
                    <span>Firebase Terhubung</span>
                </div>

                <!-- Emergency Cutoff Button -->
                <form action="{{ route('dashboard.emergency_stop') }}" method="POST" onsubmit="return confirm('PERINGATAN: Apakah Anda yakin ingin mematikan SELURUH relay secara darurat?')">
                    @csrf
                    <button type="submit" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-rose-300 bg-rose-950/80 border border-rose-700 hover:bg-rose-900 transition shadow-sm">
                        <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                        <span>EMERGENCY STOP</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mx-8 mt-4 p-3.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center space-x-2">
                <i class="fa-solid fa-circle-check text-emerald-400"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('warning'))
            <div class="mx-8 mt-4 p-3.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 text-sm flex items-center space-x-2">
                <i class="fa-solid fa-triangle-exclamation text-amber-400"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mx-8 mt-4 p-3.5 rounded-lg bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center space-x-2">
                <i class="fa-solid fa-circle-xmark text-rose-400"></i>
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
