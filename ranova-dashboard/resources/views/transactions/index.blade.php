@extends('layouts.app')

@section('title', 'Riwayat Transaksi')
@section('page_title', 'Riwayat Transaksi QRIS')

@section('content')
<div class="space-y-6">

    <!-- Mini KPI Transaksi -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-slate-400">Total Transaksi</p>
            <p class="text-xl font-bold text-slate-100 mt-1">{{ $stats['total_count'] }} Transaksi</p>
        </div>
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-slate-400">Total Pemasukan</p>
            <p class="text-xl font-bold text-emerald-400 mt-1">Rp {{ number_format($stats['total_revenue'], 0, ',', '.') }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
            <p class="text-xs text-slate-400">Frekuensi per Slot</p>
            <p class="text-xs text-slate-300 mt-1.5 space-x-2">
                <span>S1: <b class="text-emerald-400">{{ $stats['slot1_count'] }}</b></span>
                <span>S2: <b class="text-cyan-400">{{ $stats['slot2_count'] }}</b></span>
                <span>S3: <b class="text-amber-400">{{ $stats['slot3_count'] }}</b></span>
            </p>
        </div>
        <div class="glass-card rounded-xl p-4 flex items-center justify-between">
            <div>
                <p class="text-xs text-slate-400">Status Server</p>
                <p class="text-sm font-semibold text-emerald-400 mt-1">Database Sinkron</p>
            </div>
            <i class="fa-solid fa-database text-emerald-500/40 text-2xl"></i>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="glass-card rounded-xl p-4 flex flex-wrap items-center justify-between gap-4">
        <form method="GET" action="{{ route('transactions.index') }}" class="flex flex-wrap items-center gap-3">
            <div>
                <select name="slot" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 text-xs text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                    <option value="">Semua Slot</option>
                    <option value="1" {{ request('slot') == 1 ? 'selected' : '' }}>Slot 1</option>
                    <option value="2" {{ request('slot') == 2 ? 'selected' : '' }}>Slot 2</option>
                    <option value="3" {{ request('slot') == 3 ? 'selected' : '' }}>Slot 3</option>
                </select>
            </div>

            <div>
                <select name="status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 text-xs text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-emerald-500">
                    <option value="">Semua Status</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Selesai</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="force_stopped" {{ request('status') == 'force_stopped' ? 'selected' : '' }}>Dihentikan Manual</option>
                    <option value="emergency_stopped" {{ request('status') == 'emergency_stopped' ? 'selected' : '' }}>Darurat (Overheat)</option>
                </select>
            </div>

            @if(request('slot') || request('status'))
                <a href="{{ route('transactions.index') }}" class="text-xs text-slate-400 hover:text-slate-200 underline">Reset Filter</a>
            @endif
        </form>

        <span class="text-xs text-slate-500">Menampilkan {{ $transactions->count() }} dari {{ $transactions->total() }} data</span>
    </div>

    <!-- Tabel Data Transaksi -->
    <div class="glass-card rounded-2xl overflow-hidden border border-slate-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/70 border-b border-slate-800 uppercase tracking-wider text-slate-400 font-semibold">
                    <tr>
                        <th class="py-3.5 px-4">ID</th>
                        <th class="py-3.5 px-4">Waktu Mulai</th>
                        <th class="py-3.5 px-4">Slot</th>
                        <th class="py-3.5 px-4">Nominal</th>
                        <th class="py-3.5 px-4">Durasi</th>
                        <th class="py-3.5 px-4">Status</th>
                        <th class="py-3.5 px-4">Ref Pembayaran</th>
                        <th class="py-3.5 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @forelse($transactions as $t)
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono text-slate-500">#{{ $t->id }}</td>
                            <td class="py-3.5 px-4">
                                <p class="font-medium text-slate-200">{{ $t->created_at->translatedFormat('d M Y, H:i') }}</p>
                                <p class="text-[10px] text-slate-500">{{ $t->created_at->diffForHumans() }}</p>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold 
                                    {{ $t->slot_number == 1 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : '' }}
                                    {{ $t->slot_number == 2 ? 'bg-cyan-500/10 text-cyan-400 border border-cyan-500/20' : '' }}
                                    {{ $t->slot_number == 3 ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : '' }}">
                                    Slot {{ $t->slot_number }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-semibold text-emerald-400">
                                Rp {{ number_format($t->nominal_paid, 0, ',', '.') }}
                            </td>
                            <td class="py-3.5 px-4 text-slate-400">
                                {{ round($t->duration_seconds / 60) }} Menit
                            </td>
                            <td class="py-3.5 px-4">
                                @if($t->status == 'completed')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Selesai</span>
                                @elseif($t->status == 'active')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20 animate-pulse">Aktif</span>
                                @elseif($t->status == 'force_stopped')
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Manual Stop</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Darurat</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                                {{ $t->payment_reference ?? '-' }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <form action="{{ route('transactions.destroy', $t->id) }}" method="POST" onsubmit="return confirm('Hapus riwayat transaksi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="p-1.5 text-slate-500 hover:text-rose-400 transition" title="Hapus">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-500">
                                Belum ada riwayat transaksi yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transactions->hasPages())
            <div class="p-4 border-t border-slate-800">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
