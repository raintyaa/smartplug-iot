@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('page_title', 'Riwayat Transaksi QRIS')

@section('content')
<div class="space-y-6">

    <!-- 1. Mini KPI Transaksi (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Total Transaksi -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Total Transaksi</p>
                <i class="fa-solid fa-receipt text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-white">
                {{ number_format($stats['total_count']) }} <span class="text-sm font-normal text-neutral-400">Transaksi</span>
            </p>
            <p class="mt-2 text-xs text-neutral-300">Semua slot tercatat</p>
        </div>

        <!-- Total Pemasukan -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Total Pemasukan</p>
                <i class="fa-solid fa-qrcode text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-2xl font-bold tracking-tight text-mint">
                Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}
            </p>
            <p class="mt-2 text-xs text-neutral-300">Pendapatan kotor QRIS</p>
        </div>

        <!-- Frekuensi per Slot -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Frekuensi per Slot</p>
                <i class="fa-solid fa-chart-simple text-neutral-400 text-sm"></i>
            </div>
            <div class="mt-3 flex items-center gap-2 font-mono text-xs font-semibold">
                <span class="px-2 py-1 rounded bg-surface2 border border-hairline text-neutral-200">
                    S1: <span class="text-white">{{ $stats['slot1_count'] }}</span>
                </span>
                <span class="px-2 py-1 rounded bg-surface2 border border-hairline text-neutral-200">
                    S2: <span class="text-white">{{ $stats['slot2_count'] }}</span>
                </span>
                <span class="px-2 py-1 rounded bg-surface2 border border-hairline text-neutral-200">
                    S3: <span class="text-white">{{ $stats['slot3_count'] }}</span>
                </span>
            </div>
            <p class="mt-2 text-xs text-neutral-300">Rincian penggunaan relay</p>
        </div>

        <!-- Status Server -->
        <div class="rounded-xl bg-surface border border-hairline p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-neutral-300">Status Server</p>
                <i class="fa-solid fa-database text-neutral-400 text-sm"></i>
            </div>
            <p class="mt-3 font-mono text-base font-bold tracking-tight text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-mint"></span>
                Database Sinkron
            </p>
            <p class="mt-2 text-xs text-neutral-300">Penyimpanan Transaksi Aktif</p>
        </div>
    </div>

    <!-- 2. Tabel Data Transaksi (Unified Panel, 0 Nested Cards) -->
    <div class="rounded-xl bg-surface border border-hairline overflow-hidden">
        <!-- Toolbar Filter & Counter -->
        <div class="p-4 border-b border-hairline flex flex-wrap items-center justify-between gap-4 bg-surface2/30">
            <form method="GET" action="{{ route('transactions.index') }}" class="flex flex-wrap items-center gap-3">
                <div>
                    <select name="slot" onchange="this.form.submit()" class="bg-surface2 border border-hairline text-xs font-mono text-neutral-200 rounded-lg px-3 py-2 focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                        <option value="">Semua Slot</option>
                        <option value="1" {{ request('slot') == 1 ? 'selected' : '' }}>Slot 01</option>
                        <option value="2" {{ request('slot') == 2 ? 'selected' : '' }}>Slot 02</option>
                        <option value="3" {{ request('slot') == 3 ? 'selected' : '' }}>Slot 03</option>
                    </select>
                </div>

                <div>
                    <select name="status" onchange="this.form.submit()" class="bg-surface2 border border-hairline text-xs font-mono text-neutral-200 rounded-lg px-3 py-2 focus:outline-none focus:border-mint/50 focus:ring-1 focus:ring-mint/20 transition">
                        <option value="">Semua Status</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Selesai</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="force_stopped" {{ request('status') == 'force_stopped' ? 'selected' : '' }}>Dihentikan Manual</option>
                        <option value="emergency_stopped" {{ request('status') == 'emergency_stopped' ? 'selected' : '' }}>Darurat (Overheat)</option>
                    </select>
                </div>

                @if(request('slot') || request('status'))
                    <a href="{{ route('transactions.index') }}" class="text-xs font-mono text-neutral-300 hover:text-white underline transition">Reset Filter</a>
                @endif
            </form>

            <span class="text-xs font-mono text-neutral-300">Menampilkan {{ $transactions->count() }} dari {{ $transactions->total() }} data</span>
        </div>

        <!-- Table Data -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-surface2/60 border-b border-hairline uppercase tracking-wider text-neutral-300 font-mono font-semibold">
                    <tr>
                        <th class="py-3 px-4">ID</th>
                        <th class="py-3 px-4">Waktu Mulai</th>
                        <th class="py-3 px-4">Slot</th>
                        <th class="py-3 px-4">Nominal</th>
                        <th class="py-3 px-4">Durasi</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Ref Pembayaran</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hairline text-neutral-200">
                    @forelse($transactions as $t)
                        <tr class="hover:bg-white/[0.02] transition">
                            <td class="py-3.5 px-4 font-mono text-neutral-300">#{{ str_pad($t->id, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="py-3.5 px-4">
                                <p class="font-mono text-neutral-200">{{ $t->created_at->translatedFormat('d M Y, H:i') }}</p>
                                <p class="text-[11px] text-neutral-400">{{ $t->created_at->diffForHumans() }}</p>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="font-mono px-2 py-0.5 rounded text-[11px] font-semibold bg-surface2 border border-hairline text-neutral-200">
                                    SLOT 0{{ $t->slot_number }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono font-bold text-mint">
                                Rp {{ number_format($t->nominal_paid, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-neutral-300">
                                {{ round($t->duration_seconds / 60) }} Menit
                            </td>
                            <td class="py-3.5 px-4">
                                @if($t->status == 'completed')
                                    <span class="font-mono px-2 py-0.5 rounded text-[11px] font-semibold bg-mint/10 border border-mint/25 text-mint">Selesai</span>
                                @elseif($t->status == 'active')
                                    <span class="font-mono px-2 py-0.5 rounded text-[11px] font-semibold bg-mint/10 border border-mint/25 text-mint animate-pulse">Aktif</span>
                                @elseif($t->status == 'force_stopped')
                                    <span class="font-mono px-2 py-0.5 rounded text-[11px] font-semibold bg-amber/10 border border-amber/25 text-amber">Manual Stop</span>
                                @else
                                    <span class="font-mono px-2 py-0.5 rounded text-[11px] font-semibold bg-crimson/10 border border-crimson/25 text-crimson">Darurat</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono text-[11px] text-neutral-300">
                                {{ $t->payment_reference ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <form action="{{ route('transactions.destroy', $t->id) }}" method="POST" onsubmit="return confirm('Hapus riwayat transaksi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-neutral-400 hover:text-crimson transition" title="Hapus">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-neutral-400 font-mono text-xs">
                                <i class="fa-solid fa-inbox text-2xl text-neutral-400 mb-2 block"></i>
                                Belum ada riwayat transaksi yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-4 border-t border-hairline bg-surface2/30">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
