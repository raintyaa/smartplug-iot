@extends('layouts.app')

@section('title', 'Laporan & Finansial')
@section('page_title', 'Analisis Laba Rugi & Efisiensi Energi')

@section('content')
<div class="space-y-6">

    <!-- Header Filter Periode -->
    <div class="flex items-center justify-between">
        <p class="text-xs text-slate-400">Analisis perbandingan pendapatan sewa QRIS terhadap biaya konsumsi listrik PLN (Tarif R-1/TR 1.300 VA).</p>
        <div class="flex items-center space-x-2">
            <a href="{{ route('reports.index', ['days' => 7]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $days == 7 ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">7 Hari</a>
            <a href="{{ route('reports.index', ['days' => 14]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $days == 14 ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">14 Hari</a>
            <a href="{{ route('reports.index', ['days' => 30]) }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $days == 30 ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}">30 Hari</a>
        </div>
    </div>

    <!-- Ringkasan Neraca Finansial -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Pendapatan Kotor -->
        <div class="glass-card rounded-2xl p-5 border border-slate-800">
            <p class="text-xs text-slate-400 font-medium">Total Pendapatan QRIS</p>
            <p class="text-2xl font-extrabold text-emerald-400 mt-1">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</p>
            <p class="text-[11px] text-slate-500 mt-1">Akumulasi sewa masuk</p>
        </div>

        <!-- Biaya Listrik PLN -->
        <div class="glass-card rounded-2xl p-5 border border-slate-800">
            <p class="text-xs text-slate-400 font-medium">Total Beban Listrik PLN</p>
            <p class="text-2xl font-extrabold text-amber-400 mt-1">Rp {{ number_format($totalPlnCost, 0, ',', '.') }}</p>
            <p class="text-[11px] text-slate-500 mt-1">{{ number_format($totalKwh, 3) }} kWh × Rp {{ number_format($plnTariff, 0) }}</p>
        </div>

        <!-- Laba Bersih -->
        <div class="glass-card rounded-2xl p-5 border border-slate-800">
            <p class="text-xs text-slate-400 font-medium">Laba Bersih (Net Profit)</p>
            <p class="text-2xl font-extrabold {{ $netProfit >= 0 ? 'text-emerald-400' : 'text-rose-400' }} mt-1">
                Rp {{ number_format($netProfit, 0, ',', '.') }}
            </p>
            <p class="text-[11px] text-slate-500 mt-1">Pendapatan - Biaya PLN</p>
        </div>

        <!-- Margin Keuntungan -->
        <div class="glass-card rounded-2xl p-5 border border-slate-800">
            <p class="text-xs text-slate-400 font-medium">Margin Keuntungan</p>
            <p class="text-2xl font-extrabold text-cyan-400 mt-1">{{ $marginPct }}%</p>
            <p class="text-[11px] text-slate-500 mt-1">Rasio efisiensi profitabilitas</p>
        </div>
    </div>

    <!-- Grafik Analisis (Chart.js) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Grafik Tren Pendapatan Harian -->
        <div class="glass-card rounded-2xl p-6 md:col-span-2 border border-slate-800">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-bold text-slate-100 flex items-center space-x-2">
                    <i class="fa-solid fa-chart-column text-emerald-400"></i>
                    <span>Tren Pendapatan Harian ({{ $days }} Hari Terakhir)</span>
                </h3>
            </div>
            <div class="h-64">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Distribusi Pendapatan per Slot -->
        <div class="glass-card rounded-2xl p-6 border border-slate-800 flex flex-col justify-between">
            <div class="mb-4">
                <h3 class="text-sm font-bold text-slate-100 flex items-center space-x-2">
                    <i class="fa-solid fa-chart-pie text-cyan-400"></i>
                    <span>Kontribusi per Stop Kontak</span>
                </h3>
            </div>
            <div class="h-56 relative flex items-center justify-center">
                <canvas id="slotChart"></canvas>
            </div>
            <div class="grid grid-cols-3 gap-2 text-center text-xs mt-4 pt-4 border-t border-slate-800/60">
                <div>
                    <span class="text-emerald-400 font-bold block">Slot 1</span>
                    <span class="text-[10px] text-slate-400">Rp {{ number_format($slotDistribution['Slot 1'], 0, ',', '.') }}</span>
                </div>
                <div>
                    <span class="text-cyan-400 font-bold block">Slot 2</span>
                    <span class="text-[10px] text-slate-400">Rp {{ number_format($slotDistribution['Slot 2'], 0, ',', '.') }}</span>
                </div>
                <div>
                    <span class="text-amber-400 font-bold block">Slot 3</span>
                    <span class="text-[10px] text-slate-400">Rp {{ number_format($slotDistribution['Slot 3'], 0, ',', '.') }}</span>
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
                    backgroundColor: 'rgba(34, 197, 94, 0.4)',
                    borderColor: 'rgb(34, 197, 94)',
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
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11 },
                            callback: value => 'Rp ' + value.toLocaleString('id-ID')
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
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(6, 182, 212, 0.8)',
                        'rgba(245, 158, 11, 0.8)'
                    ],
                    borderColor: '#0f172a',
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
