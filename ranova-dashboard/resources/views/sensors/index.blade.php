@extends('layouts.app')

@section('title', 'Telemetri Sensor')
@section('page_title', 'Telemetri Sensor PZEM-004T & DHT22')

@section('content')
<div class="space-y-6">

    <!-- Ringkasan Angka Sensor Terakhir (5 Cards) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Tegangan Listrik -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Tegangan Listrik</p>
            <p class="text-2xl font-bold font-mono tracking-tight text-white mt-2">
                {{ number_format($latestPower->voltage ?? 220, 1) }} <span class="text-sm font-normal text-neutral-400">V</span>
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">AC 50Hz</span>
        </div>

        <!-- Arus Beban -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Arus Beban</p>
            <p class="text-2xl font-bold font-mono tracking-tight text-white mt-2">
                {{ number_format($latestPower->current ?? 0, 2) }} <span class="text-sm font-normal text-neutral-400">A</span>
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">Donat CT</span>
        </div>

        <!-- Daya Nyata -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Daya Nyata</p>
            <p class="text-2xl font-bold font-mono tracking-tight text-mint mt-2">
                {{ number_format($latestPower->power ?? 0, 1) }} <span class="text-sm font-normal text-neutral-400">W</span>
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">Beban Realtime</span>
        </div>

        <!-- Suhu Box Internal -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Suhu Box Internal</p>
            <p class="text-2xl font-bold font-mono tracking-tight text-amber mt-2">
                {{ number_format($latestTemp->temperature ?? 29, 1) }}<span class="text-sm font-normal text-neutral-400">°C</span>
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">Sensor DHT22</span>
        </div>

        <!-- Kelembaban -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center col-span-2 md:col-span-1">
            <p class="text-xs font-medium text-neutral-300">Kelembaban Udara</p>
            <p class="text-2xl font-bold font-mono tracking-tight text-white mt-2">
                {{ number_format($latestTemp->humidity ?? 65, 0) }}<span class="text-sm font-normal text-neutral-400">%</span>
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">Udara Box</span>
        </div>
    </div>

    <!-- Grafik 1: Daya Listrik (Watt) & Tegangan (Volt) -->
    <div class="rounded-xl bg-surface border border-hairline p-6">
        <h2 class="text-base font-semibold text-white tracking-tight mb-5 flex items-center space-x-2">
            <i class="fa-solid fa-bolt text-mint text-sm"></i>
            <span>Histori Daya Nyata (Watt) &amp; Tegangan (PZEM-004T)</span>
        </h2>
        <div class="h-64">
            <canvas id="powerTimelineChart"></canvas>
        </div>
    </div>

    <!-- Grafik 2: Suhu & Kelembaban (DHT22) -->
    <div class="rounded-xl bg-surface border border-hairline p-6">
        <h2 class="text-base font-semibold text-white tracking-tight mb-5 flex items-center space-x-2">
            <i class="fa-solid fa-temperature-half text-amber text-sm"></i>
            <span>Histori Suhu &amp; Kelembaban Udara Internal Box (DHT22)</span>
        </h2>
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
                        borderColor: '#05DF72',
                        backgroundColor: 'rgba(5, 223, 114, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Tegangan (Volt)',
                        data: {!! json_encode($powerChartVolts) !!},
                        borderColor: '#A3A3A3',
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
                plugins: {
                    legend: {
                        labels: {
                            color: '#D4D4D4',
                            font: { family: '"JetBrains Mono", monospace', size: 11 }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(40, 40, 50, 0.6)' },
                        ticks: { color: '#A3A3A3', font: { family: '"JetBrains Mono", monospace', size: 11 } }
                    },
                    y: {
                        position: 'left',
                        grid: { color: 'rgba(40, 40, 50, 0.6)' },
                        ticks: {
                            color: '#05DF72',
                            font: { family: '"JetBrains Mono", monospace', size: 11 },
                            callback: v => v + ' W'
                        }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#A3A3A3',
                            font: { family: '"JetBrains Mono", monospace', size: 11 },
                            callback: v => v + ' V'
                        }
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
                        borderColor: '#F59E0B',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Kelembaban (%)',
                        data: {!! json_encode($humidityChartValues) !!},
                        borderColor: '#38BDF8',
                        fill: false,
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#D4D4D4',
                            font: { family: '"JetBrains Mono", monospace', size: 11 }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(40, 40, 50, 0.6)' },
                        ticks: { color: '#A3A3A3', font: { family: '"JetBrains Mono", monospace', size: 11 } }
                    },
                    y: {
                        position: 'left',
                        grid: { color: 'rgba(40, 40, 50, 0.6)' },
                        ticks: {
                            color: '#F59E0B',
                            font: { family: '"JetBrains Mono", monospace', size: 11 },
                            callback: v => v + ' °C'
                        }
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: '#38BDF8',
                            font: { family: '"JetBrains Mono", monospace', size: 11 },
                            callback: v => v + ' %'
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
