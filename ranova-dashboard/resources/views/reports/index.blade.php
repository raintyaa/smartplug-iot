@extends('layouts.app')

@section('title', 'Laporan & Finansial')
@section('page_title', 'Analisis Laba Rugi & Efisiensi Energi')

@section('content')
<div class="space-y-6">

    <!-- Header Filter Periode -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <p class="text-xs text-neutral-300 max-w-xl leading-relaxed">
            Perbandingan pendapatan sewa QRIS terhadap estimasi biaya konsumsi listrik PLN (Tarif Golongan R-1/TR 1.300 VA).
        </p>
        <div class="flex items-center space-x-2">
            <a href="{{ route('reports.index', ['days' => 7]) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-mono font-medium transition {{ $days == 7 ? 'bg-mint/15 text-mint border border-mint/30' : 'bg-surface2 text-neutral-300 hover:text-white border border-hairline' }}">7 Hari</a>
            <a href="{{ route('reports.index', ['days' => 14]) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-mono font-medium transition {{ $days == 14 ? 'bg-mint/15 text-mint border border-mint/30' : 'bg-surface2 text-neutral-300 hover:text-white border border-hairline' }}">14 Hari</a>
            <a href="{{ route('reports.index', ['days' => 30]) }}" class="px-3.5 py-1.5 rounded-lg text-xs font-mono font-medium transition {{ $days == 30 ? 'bg-mint/15 text-mint border border-mint/30' : 'bg-surface2 text-neutral-300 hover:text-white border border-hairline' }}">30 Hari</a>
        </div>
    </div>

    <!-- Ringkasan Neraca Finansial (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Pendapatan Kotor -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Total Pendapatan QRIS</p>
                <i class="fa-solid fa-qrcode text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-mint">
                Rp {{ number_format($totalRevenue, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300">Akumulasi sewa masuk</p>
        </div>

        <!-- Biaya Listrik PLN -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Total Beban Listrik PLN</p>
                <i class="fa-solid fa-receipt text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-white">
                Rp {{ number_format($totalPlnCost, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300 font-mono">{{ number_format($totalKwh, 3, ',', '.') }} kWh &times; Rp {{ number_format($plnTariff, 0, ',', '.') }}</p>
        </div>

        <!-- Laba Bersih -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Laba Bersih (Net Profit)</p>
                <i class="fa-solid fa-wallet text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight {{ $netProfit >= 0 ? 'text-mint' : 'text-crimson' }}">
                Rp {{ number_format($netProfit, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300">Pendapatan dikurangi Biaya PLN</p>
        </div>

        <!-- Margin Keuntungan -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Margin Keuntungan</p>
                <i class="fa-solid fa-percent text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-white">
                {{ $marginPct }}%
            </p>
            <p class="mt-2 text-xs text-neutral-300">Rasio efisiensi profitabilitas</p>
        </div>
    </div>

    <!-- Grafik Analisis (Chart.js) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Grafik Tren Pendapatan Harian -->
        <div class="rounded-xl bg-surface border border-hairline p-6 lg:col-span-2">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-base font-semibold text-white tracking-tight flex items-center space-x-2">
                    <i class="fa-solid fa-chart-column text-mint text-sm"></i>
                    <span>Tren Pendapatan Harian ({{ $days }} Hari Terakhir)</span>
                </h2>
            </div>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Distribusi Pendapatan per Slot -->
        <div class="rounded-xl bg-surface border border-hairline p-6 flex flex-col justify-between">
            <div class="mb-4">
                <h2 class="text-base font-semibold text-white tracking-tight flex items-center space-x-2">
                    <i class="fa-solid fa-chart-pie text-neutral-400 text-sm"></i>
                    <span>Kontribusi per Stop Kontak</span>
                </h2>
            </div>
            <div class="h-52 relative flex items-center justify-center">
                <canvas id="slotChart"></canvas>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-5 pt-4 border-t border-hairline text-center">
                <div class="p-2.5 rounded-lg bg-surface2 border border-hairline">
                    <span class="font-mono text-xs font-semibold text-mint block">SLOT 01</span>
                    <span class="font-mono text-xs text-neutral-300 mt-1 block">Rp {{ number_format($slotDistribution['Slot 1'], 0, ',', '.') }}</span>
                </div>
                <div class="p-2.5 rounded-lg bg-surface2 border border-hairline">
                    <span class="font-mono text-xs font-semibold text-amber block">SLOT 02</span>
                    <span class="font-mono text-xs text-neutral-300 mt-1 block">Rp {{ number_format($slotDistribution['Slot 2'], 0, ',', '.') }}</span>
                </div>
                <div class="p-2.5 rounded-lg bg-surface2 border border-hairline">
                    <span class="font-mono text-xs font-semibold text-neutral-300 block">SLOT 03</span>
                    <span class="font-mono text-xs text-neutral-300 mt-1 block">Rp {{ number_format($slotDistribution['Slot 3'], 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Grafik Pendapatan Harian
        const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctxRevenue, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    label: 'Pendapatan (Rp)',
                    data: {!! json_encode($chartRevenues) !!},
                    backgroundColor: 'rgba(5, 223, 114, 0.25)',
                    borderColor: '#05DF72',
                    borderWidth: 1.5,
                    borderRadius: 6,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(40, 40, 50, 0.6)' },
                        ticks: { color: '#A3A3A3', font: { family: '"JetBrains Mono", monospace', size: 11 } }
                    },
                    y: {
                        grid: { color: 'rgba(40, 40, 50, 0.6)' },
                        ticks: {
                            color: '#A3A3A3',
                            font: { family: '"JetBrains Mono", monospace', size: 11 },
                            callback: value => 'Rp ' + Number(value).toLocaleString('id-ID')
                        }
                    }
                }
            }
        });

        // 2. Grafik Donut Distribusi Slot
        const ctxSlot = document.getElementById('slotChart').getContext('2d');
        new Chart(ctxSlot, {
            type: 'doughnut',
            data: {
                labels: ['Slot 1', 'Slot 2', 'Slot 3'],
                datasets: [{
                    data: [
                        {{ $slotDistribution['Slot 1'] }},
                        {{ $slotDistribution['Slot 2'] }},
                        {{ $slotDistribution['Slot 3'] }}
                    ],
                    backgroundColor: [
                        '#05DF72',
                        '#F59E0B',
                        '#64748B'
                    ],
                    borderColor: '#131318',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    });
</script>
@endpush
