<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $from = Carbon::parse($request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();

        $sales = Sale::with('user')
            ->whereBetween('sold_at', [$from, $to])
            ->orderBy('sold_at')
            ->get();

        $totalRevenue = $sales->sum('total_amount');
        $byPayment    = $sales->groupBy('payment_method')->map(fn ($g) => [
            'count' => $g->count(),
            'total' => $g->sum('total_amount'),
        ]);

        return view('reports.sales', compact('sales', 'from', 'to', 'totalRevenue', 'byPayment'));
    }
}
