<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                    'mint-hover': '#04C966',
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
