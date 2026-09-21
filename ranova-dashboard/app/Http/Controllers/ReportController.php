<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\PowerReading;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $plnTariff = (float) Setting::get('tariff_pln_per_kwh', 1444.70);

        // Filter rentang hari (default 7 hari terakhir)
        $days = (int) $request->get('days', 7);
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        // 1. Ringkasan Total Akumulasi
        $totalRevenue = Transaction::whereIn('status', ['completed', 'active'])->sum('nominal_paid');
        $latestPower = PowerReading::latest('recorded_at')->first();
        $totalKwh = $latestPower ? (float)$latestPower->energy : 0.0;
        $totalPlnCost = $totalKwh * $plnTariff;
        $netProfit = $totalRevenue - $totalPlnCost;
        $marginPct = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

        // 2. Data Grafik Harian (Pendapatan & Transaksi)
        $dailyTransactions = Transaction::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(nominal_paid) as daily_revenue'),
                DB::raw('COUNT(id) as total_tx')
            )
            ->where('created_at', '>=', $startDate)
            ->whereIn('status', ['completed', 'active'])
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get()
            ->keyBy('date');

        $chartLabels = [];
        $chartRevenues = [];
        $chartTxCounts = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $dateKey = Carbon::now()->subDays($i)->format('Y-m-d');
            $displayLabel = Carbon::now()->subDays($i)->translatedFormat('d M');
            $chartLabels[] = $displayLabel;
            $chartRevenues[] = (int) ($dailyTransactions[$dateKey]->daily_revenue ?? 0);
            $chartTxCounts[] = (int) ($dailyTransactions[$dateKey]->total_tx ?? 0);
        }

        // 3. Distribusi Penggunaan per Slot
        $slotDistribution = [
            'Slot 1' => Transaction::where('slot_number', 1)->whereIn('status', ['completed', 'active'])->sum('nominal_paid'),
            'Slot 2' => Transaction::where('slot_number', 2)->whereIn('status', ['completed', 'active'])->sum('nominal_paid'),
            'Slot 3' => Transaction::where('slot_number', 3)->whereIn('status', ['completed', 'active'])->sum('nominal_paid'),
        ];

        return view('reports.index', compact(
            'totalRevenue',
            'totalKwh',
            'totalPlnCost',
            'netProfit',
            'marginPct',
            'plnTariff',
            'days',
            'chartLabels',
            'chartRevenues',
            'chartTxCounts',
            'slotDistribution'
        ));
    }
}
