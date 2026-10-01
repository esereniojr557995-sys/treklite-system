<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Staff only handle sales; the dashboard is for the Owner.
        if (! auth()->user()->hasFullAccess()) {
            return redirect()->route('sales.create');
        }

        // 1. Sales summary
        $totals = fn ($from) => Sale::where('sold_at', '>=', $from)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total, COUNT(*) as cnt')
            ->first();

        $today = $totals(today());
        $week  = $totals(now()->startOfWeek());
        $month = $totals(now()->startOfMonth());

        $byPayment = Sale::where('sold_at', '>=', now()->startOfMonth())
            ->selectRaw('payment_method, SUM(total_amount) as total, COUNT(*) as cnt')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        // 2. Inventory summary
        $productCount = Product::count();
        $unitsOnHand  = (int) ProductVariant::sum('quantity');
        $stockValue   = (float) DB::table('product_variants as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->selectRaw('COALESCE(SUM(v.quantity * COALESCE(v.price, p.price)), 0) as value')
            ->value('value');

        // 3. Needs attention
        $outOfStock = ProductVariant::with('product')->where('quantity', '<=', 0)->get();
        $lowStock   = ProductVariant::with('product')
            ->where('quantity', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->orderBy('quantity')
            ->get();

        // 4. Recent transactions
        $recentSales     = Sale::with('user')->latest('sold_at')->limit(6)->get();
        $recentMovements = StockMovement::with('variant.product', 'user')->latest()->limit(6)->get();

        return view('dashboard', compact(
            'today', 'week', 'month', 'byPayment',
            'productCount', 'unitsOnHand', 'stockValue',
            'outOfStock', 'lowStock',
            'recentSales', 'recentMovements'
        ));
    }
}
