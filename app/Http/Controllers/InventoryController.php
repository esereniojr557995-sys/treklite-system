<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Per-branch stock view + manual restock (e.g. after a supplier delivery
 * is received and audited by the Co-Owner — see Chapter 1, Purchasing and
 * Supplier Management).
 */
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Inventory::with(['product', 'branch']);

        // Staff only see their own branch; Owner/Co-Owner see all
        // (optionally filtered via a ?branch_id= query param).
        if ($user->isStaff()) {
            $query->where('branch_id', $user->branch_id);
        } elseif ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        $inventories = $query->join('products', 'products.id', '=', 'inventories.product_id')
            ->orderBy('products.name')
            ->select('inventories.*')
            ->paginate(20);

        return view('inventory.index', compact('inventories'));
    }

    /** Add received stock (e.g. from a supplier delivery). */
    public function restock(Request $request, Inventory $inventory)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $inventory->increment('quantity', $data['quantity']);

        return back()->with('status', "Added {$data['quantity']} units to {$inventory->product->name}.");
    }
}
