@extends('layouts.app')

@section('title', 'Telemetri Sensor')
@section('page_title', 'Telemetri Sensor PZEM-004T & DHT22')

@section('content')
<div class="space-y-6">

    <!-- Ringkasan Angka Sensor Terakhir -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="glass-card rounded-2xl p-4 text-center">
            <p class="text-xs text-slate-400">Tegangan Listrik (V)</p>
            <p class="text-2xl font-bold text-slate-100 mt-1">{{ number_format($latestPower->voltage ?? 220, 1) }} V</p>
            <span class="text-[10px] text-slate-500">AC 50Hz</span>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <p class="text-xs text-slate-400">Arus Beban (A)</p>
            <p class="text-2xl font-bold text-cyan-400 mt-1">{{ number_format($latestPower->current ?? 0, 2) }} A</p>
            <span class="text-[10px] text-slate-500">Donat CT</span>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <p class="text-xs text-slate-400">Daya Nyata (W)</p>
            <p class="text-2xl font-bold text-emerald-400 mt-1">{{ number_format($latestPower->power ?? 0, 1) }} W</p>
            <span class="text-[10px] text-slate-500">Beban Realtime</span>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <p class="text-xs text-slate-400">Suhu Box Internal</p>
            <p class="text-2xl font-bold text-amber-400 mt-1">{{ number_format($latestTemp->temperature ?? 29, 1) }}°C</p>
            <span class="text-[10px] text-slate-500">Sensor DHT22</span>
        </div>
        <div class="glass-card rounded-2xl p-4 text-center">
            <p class="text-xs text-slate-400">Kelembaban</p>
            <p class="text-2xl font-bold text-blue-400 mt-1">{{ number_format($latestTemp->humidity ?? 65, 0) }}%</p>
            <span class="text-[10px] text-slate-500">Udara Box</span>
        </div>
    </div>

    <!-- Grafik 1: Daya Listrik (Watt) & Tegangan (Volt) -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <h3 class="text-sm font-bold text-slate-100 mb-4 flex items-center space-x-2">
            <i class="fa-solid fa-bolt text-emerald-400"></i>
            <span>Histori Daya Nyata (Watt) & Tegangan (PZEM-004T)</span>
        </h3>
        <div class="h-64">
            <canvas id="powerTimelineChart"></canvas>
        </div>
    </div>

    <!-- Grafik 2: Suhu & Kelembaban (DHT22) -->
    <div class="glass-card rounded-2xl p-6 border border-slate-800">
        <h3 class="text-sm font-bold text-slate-100 mb-4 flex items-center space-x-2">
            <i class="fa-solid fa-temperature-half text-amber-400"></i>
            <span>Histori Suhu & Kelembaban Udara Internal Box (DHT22)</span>
        </h3>
        <div class="h-64">
            <canvas id="tempTimelineChart"></canvas>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // 1. Grafik Daya (Watt) & Tegangan
        const ctxPower = document.getElementById('powerTimelineChart').getContext('2d');
        new Chart(ctxPower, {
            type: 'line',
            data: {
                labels: {!! json_encode($powerChartLabels) !!},
                datasets: [
                    {
                        label: 'Daya (Watt)',
                        data: {!! json_encode($powerChartWatts) !!},
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Tegangan (Volt)',
                        data: {!! json_encode($powerChartVolts) !!},
                        borderColor: 'rgb(6, 182, 212)',
                        borderDash: [5, 5],
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8' } },
                    y: {
                        position: 'left',
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#22c55e', callback: v => v + ' W' }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { color: '#06b6d4', callback: v => v + ' V' }
                    }
                }
            }
        });

        // 2. Grafik Suhu & Kelembaban
        const ctxTemp = document.getElementById('tempTimelineChart').getContext('2d');
        new Chart(ctxTemp, {
            type: 'line',
            data: {
                labels: {!! json_encode($tempChartLabels) !!},
                datasets: [
                    {
                        label: 'Suhu (°C)',
                        data: {!! json_encode($tempChartValues) !!},
                        borderColor: 'rgb(245, 158, 11)',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Kelembaban (%)',
                        data: {!! json_encode($humidityChartValues) !!},
                        borderColor: 'rgb(59, 130, 246)',
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { grid: { color: 'rgba(255, 255, 255, 0.05)' }, ticks: { color: '#94a3b8' } },
                    y: {
                        position: 'left',
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#f59e0b', callback: v => v + ' °C' }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { color: '#3b82f6', callback: v => v + ' %' }
                    }
                }
            }
        });
    });
</script>
@endpush
