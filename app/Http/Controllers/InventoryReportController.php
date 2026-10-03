<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use Illuminate\Http\Request;

/** Owner-only inventory report: stock on hand per product variant, with low-stock items. */
class InventoryReportController extends Controller
{
    public function index(Request $request)
    {
        $onlyAttention = $request->boolean('attention');

        $variants = ProductVariant::with('product')
            ->when($onlyAttention, fn ($q) => $q->whereColumn('quantity', '<=', 'low_stock_threshold'))
            ->get()
            ->sortBy(fn ($v) => strtolower($v->product->name . ' ' . $v->label))
            ->values();

        $totalUnits = (int) $variants->sum('quantity');
        $totalValue = (float) $variants->sum(fn ($v) => $v->quantity * $v->selling_price);
        $lowCount   = $variants->filter(fn ($v) => $v->isLowStock())->count();
        $outCount   = $variants->filter(fn ($v) => $v->isOutOfStock())->count();

        return view('reports.inventory', compact('variants', 'onlyAttention', 'totalUnits', 'totalValue', 'lowCount', 'outCount'));
    }
}
