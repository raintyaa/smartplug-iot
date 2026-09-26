<!DOCTYPE html>
<html lang="id" class="h-full bg-base text-neutral-200">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Operator — RANOVA Smart Plug</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
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
                        'mint-hover': '#04C966',
                        amber: '#F59E0B',
                        crimson: '#EF4444',
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #0A0A0D; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-base text-neutral-200 antialiased selection:bg-mint/30">

    <div class="w-full max-w-md">
        <!-- Logo & Title -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl border border-hairline bg-surface2 mb-3 p-1">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-xl font-bold tracking-tight text-white">RANOVA SMART PLUG</h1>
            <p class="text-xs text-neutral-400 mt-1">Portal Pemantauan & Kontrol Operator</p>
        </div>

        <!-- Login Card -->
        <div class="bg-surface border border-hairline p-7 rounded-xl">
            @if(isset($errors) && $errors->any())
                <div class="mb-5 p-3 rounded-lg bg-crimson/10 border border-crimson/30 text-crimson text-xs flex items-center space-x-2">
                    <i class="fa-solid fa-circle-exclamation text-crimson"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-medium text-neutral-300 mb-1.5">Email Operator</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-neutral-500 text-sm">
                            <i class="fa-solid fa-envelope"></i>
                        </span>
                        <input type="email" name="email" id="email" value="{{ old('email', 'admin@ranova.id') }}" required
                            class="w-full pl-10 pr-4 py-2.5 bg-surface2 border border-hairline rounded-lg text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-neutral-300 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-neutral-500 text-sm">
                            <i class="fa-solid fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" value="admin123" required
                            class="w-full pl-10 pr-4 py-2.5 bg-surface2 border border-hairline rounded-lg text-sm text-white placeholder-neutral-500 focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs pt-1">
                    <label class="flex items-center text-neutral-400 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-3.5 h-3.5 rounded border-hairline bg-surface2 accent-[#05DF72] mr-2">
                        <span>Ingat Sesi Ini</span>
                    </label>
                    <span class="text-neutral-400 font-mono text-[11px]">Default: admin123</span>
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-lg bg-mint hover:bg-mint-hover text-[#0A0A0D] font-semibold text-xs transition-colors flex items-center justify-center space-x-2 mt-2">
                    <span>Masuk ke Dashboard</span>
                    <i class="fa-solid fa-arrow-right text-[11px]"></i>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-xs text-neutral-400 mt-6">
            &copy; 2026 RANOVA IoT Project — Tugas Praktikum Semester 5
        </p>
    </div>

</body>
</html>
