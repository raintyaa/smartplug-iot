@extends('layouts.app')

@section('title', 'Telemetri Sensor')
@section('page_title', 'TELEMETRI SENSOR')

@section('content')
<div class="space-y-6">

    <!-- Ringkasan Angka Sensor Terakhir (5 Cards) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Tegangan Listrik -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Tegangan Listrik</p>
            <p id="sensor-page-volt" class="text-2xl font-bold font-mono tracking-tight text-white mt-2">
                @if($latestPower && isset($latestPower->voltage))
                    {{ number_format($latestPower->voltage, 1) }} <span class="text-sm font-normal text-neutral-400">V</span>
                @else
                    <span class="text-neutral-500">—</span>
                @endif
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">
                {{ $latestPower ? 'AC 50Hz' : 'Belum ada data' }}
            </span>
        </div>

        <!-- Arus Beban -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Arus Beban</p>
            <p id="sensor-page-curr" class="text-2xl font-bold font-mono tracking-tight text-white mt-2">
                @if($latestPower && isset($latestPower->current))
                    {{ number_format($latestPower->current, 2) }} <span class="text-sm font-normal text-neutral-400">A</span>
                @else
                    <span class="text-neutral-500">—</span>
                @endif
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">
                {{ $latestPower ? 'Donat CT' : 'Belum ada data' }}
            </span>
        </div>

        <!-- Daya Nyata -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Daya Nyata</p>
            <p id="sensor-page-power" class="text-2xl font-bold font-mono tracking-tight text-mint mt-2">
                @if($latestPower && isset($latestPower->power))
                    {{ number_format($latestPower->power, 1) }} <span class="text-sm font-normal text-neutral-400">W</span>
                @else
                    <span class="text-neutral-500">—</span>
                @endif
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">
                {{ $latestPower ? 'Beban Realtime' : 'Belum ada data' }}
            </span>
        </div>

        <!-- Suhu Box Internal -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center">
            <p class="text-xs font-medium text-neutral-300">Suhu Box Internal</p>
            <p id="sensor-page-temp" class="text-2xl font-bold font-mono tracking-tight text-amber mt-2">
                @if($latestTemp && isset($latestTemp->temperature))
                    {{ number_format($latestTemp->temperature, 1) }}<span class="text-sm font-normal text-neutral-400">°C</span>
                @else
                    <span class="text-neutral-500">—</span>
                @endif
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">
                {{ $latestTemp ? 'Sensor DHT22' : 'Belum ada data' }}
            </span>
        </div>

        <!-- Kelembaban -->
        <div class="rounded-xl bg-surface border border-hairline p-5 text-center col-span-2 md:col-span-1">
            <p class="text-xs font-medium text-neutral-300">Kelembaban Udara</p>
            <p id="sensor-page-humid" class="text-2xl font-bold font-mono tracking-tight text-white mt-2">
                @if($latestTemp && isset($latestTemp->humidity))
                    {{ number_format($latestTemp->humidity, 0) }}<span class="text-sm font-normal text-neutral-400">%</span>
                @else
                    <span class="text-neutral-500">—</span>
                @endif
            </p>
            <span class="mt-2.5 inline-block px-2.5 py-0.5 rounded bg-surface2 border border-hairline text-xs font-mono text-neutral-300">
                {{ $latestTemp ? 'Udara Box' : 'Belum ada data' }}
            </span>
        </div>
    </div>

    <!-- Grafik 1: Daya Listrik (Watt) & Tegangan (Volt) -->
    <div class="rounded-xl bg-surface border border-hairline p-6">
        <h2 class="text-base font-semibold text-white tracking-tight mb-5 flex items-center space-x-2">
            <i class="fa-solid fa-bolt text-mint text-sm" aria-hidden="true"></i>
            <span>Histori Daya Nyata (Watt) &amp; Tegangan (PZEM-004T)</span>
        </h2>
        <div class="h-64">
            <canvas id="powerTimelineChart" role="img" aria-label="Grafik garis histori daya dalam Watt dan tegangan dalam Volt dari sensor PZEM-004T. Ringkasan data tersedia pada tabel berikut."></canvas>
        </div>
        <details class="mt-4">
            <summary class="text-xs font-mono text-neutral-300 cursor-pointer hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base rounded w-fit px-1 py-1">Tampilkan ringkasan data daya (teks)</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <caption class="sr-only">Ringkasan 10 catatan terakhir daya dan tegangan</caption>
                    <thead class="border-b border-hairline uppercase tracking-wider text-neutral-300 font-mono font-medium">
                        <tr><th class="pb-2 px-3" scope="col">Waktu</th><th class="pb-2 px-3" scope="col">Daya (W)</th><th class="pb-2 px-3" scope="col">Tegangan (V)</th></tr>
                    </thead>
                    <tbody class="divide-y divide-hairline font-mono text-neutral-200">
                        @php
                            $pLabels = collect($powerChartLabels ?? [])->toArray();
                            $pWatts  = collect($powerChartWatts ?? [])->toArray();
                            $pVolts  = collect($powerChartVolts ?? [])->toArray();
                            $powerRows = [];
                            foreach ($pLabels as $idx => $lbl) {
                                $powerRows[] = [$lbl, $pWatts[$idx] ?? 0, $pVolts[$idx] ?? 0];
                            }
                            $powerSlice = array_slice($powerRows, -10);
                        @endphp
                        @forelse($powerSlice as $row)
                            <tr><td class="py-2 px-3">{{ $row[0] }}</td><td class="py-2 px-3">{{ $row[1] }}</td><td class="py-2 px-3">{{ $row[2] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-4 px-3 text-neutral-300">Belum ada data daya tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    <!-- Grafik 2: Suhu & Kelembaban (DHT22) -->
    <div class="rounded-xl bg-surface border border-hairline p-6">
        <h2 class="text-base font-semibold text-white tracking-tight mb-5 flex items-center space-x-2">
            <i class="fa-solid fa-temperature-half text-amber text-sm" aria-hidden="true"></i>
            <span>Histori Suhu &amp; Kelembaban Udara Internal Box (DHT22)</span>
        </h2>
        <div class="h-64">
            <canvas id="tempTimelineChart" role="img" aria-label="Grafik garis histori suhu dalam derajat Celcius dan kelembaban dalam persen dari sensor DHT22. Ringkasan data tersedia pada tabel berikut."></canvas>
        </div>
        <details class="mt-4">
            <summary class="text-xs font-mono text-neutral-300 cursor-pointer hover:text-white transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-mint focus-visible:ring-offset-2 focus-visible:ring-offset-base rounded w-fit px-1 py-1">Tampilkan ringkasan data suhu (teks)</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <caption class="sr-only">Ringkasan 10 catatan terakhir suhu dan kelembaban</caption>
                    <thead class="border-b border-hairline uppercase tracking-wider text-neutral-300 font-mono font-medium">
                        <tr><th class="pb-2 px-3" scope="col">Waktu</th><th class="pb-2 px-3" scope="col">Suhu (°C)</th><th class="pb-2 px-3" scope="col">Kelembaban (%)</th></tr>
                    </thead>
                    <tbody class="divide-y divide-hairline font-mono text-neutral-200">
                        @php
                            $tLabels = collect($tempChartLabels ?? [])->toArray();
                            $tVals   = collect($tempChartValues ?? [])->toArray();
                            $tHums   = collect($humidityChartValues ?? [])->toArray();
                            $tempRows = [];
                            foreach ($tLabels as $idx => $lbl) {
                                $tempRows[] = [$lbl, $tVals[$idx] ?? 0, $tHums[$idx] ?? 0];
                            }
                            $tempSlice = array_slice($tempRows, -10);
                        @endphp
                        @forelse($tempSlice as $row)
                            <tr><td class="py-2 px-3">{{ $row[0] }}</td><td class="py-2 px-3">{{ $row[1] }}</td><td class="py-2 px-3">{{ $row[2] }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="py-4 px-3 text-neutral-300">Belum ada data suhu tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
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
