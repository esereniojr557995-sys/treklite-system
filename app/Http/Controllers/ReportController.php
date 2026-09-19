<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

/**
 * Covers the "Generate sales and inventory reports" objective.
 * This starter gives you a simple on-screen date-range report;
 * wiring up PDF/Excel export (e.g. via barryvdh/laravel-dompdf or
 * maatwebsite/excel) is a good next milestone once you have internet
 * access to install those packages via Composer.
 */
class ReportController extends Controller
{
    public function sales(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();

        $sales = Sale::with(['branch', 'items.product'])
            ->whereBetween('sold_at', [$from, $to])
            ->latest('sold_at')
            ->get();

        $totalRevenue = $sales->sum('total_amount');

        return view('reports.sales', compact('sales', 'totalRevenue', 'from', 'to'));
    }
}
