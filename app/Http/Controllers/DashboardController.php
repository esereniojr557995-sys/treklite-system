<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Owner/Manager see all branches; Staff see only their own branch.
        $salesQuery = Sale::query()->whereDate('sold_at', today());
        $inventoryQuery = Inventory::query()->with(['product', 'branch']);

        if ($user->isStaff()) {
            $salesQuery->where('branch_id', $user->branch_id);
            $inventoryQuery->where('branch_id', $user->branch_id);
        }

        $todaysSalesTotal = (clone $salesQuery)->sum('total_amount');
        $todaysSalesCount = (clone $salesQuery)->count();

        $lowStockItems = $inventoryQuery->get()->filter(fn ($inv) => $inv->isLowStock());

        return view('dashboard', [
            'todaysSalesTotal' => $todaysSalesTotal,
            'todaysSalesCount' => $todaysSalesCount,
            'lowStockItems' => $lowStockItems,
        ]);
    }
}
