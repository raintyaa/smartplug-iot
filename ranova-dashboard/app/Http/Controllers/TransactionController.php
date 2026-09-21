<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query()->latest();

        if ($request->filled('slot')) {
            $query->where('slot_number', $request->slot);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query->paginate(15)->withQueryString();

        $stats = [
            'total_count' => Transaction::count(),
            'total_revenue' => Transaction::whereIn('status', ['completed', 'active'])->sum('nominal_paid'),
            'slot1_count' => Transaction::where('slot_number', 1)->count(),
            'slot2_count' => Transaction::where('slot_number', 2)->count(),
            'slot3_count' => Transaction::where('slot_number', 3)->count(),
        ];

        return view('transactions.index', compact('transactions', 'stats'));
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return back()->with('success', 'Data transaksi berhasil dihapus.');
    }
}
