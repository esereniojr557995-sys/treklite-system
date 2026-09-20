<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * This is the digital replacement for the paper logbook described in
 * Chapter 1 (Sales Transaction Process). Recording a sale here:
 *   1. Generates an invoice number (replaces the handwritten sales invoice)
 *   2. Deducts sold quantities from that branch's inventory in real time,
 *      resolving Problem 1 (Difficulty in Monitoring and Verifying
 *      Inventory) from the Statement of the Problem
 *   3. Blocks the sale if stock is insufficient, so the recorded quantity
 *      can never drift from actual stock the way it did on paper
 *   4. Shows live per-branch stock on the POS screen itself (see create()),
 *      resolving Problem 3 (Delay in Customer Service When Checking Stock
 *      Availability)
 */
class SaleController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Sale::with(['branch', 'user'])->latest('sold_at');

        if ($user->isStaff()) {
            $query->where('branch_id', $user->branch_id);
        }

        $sales = $query->paginate(20);

        return view('sales.index', compact('sales'));
    }

    /**
     * Shows the POS/sale screen. Every product is returned together with
     * its current per-branch stock quantity, so the person on duty can see
     * live availability on screen instead of checking the paper inventory
     * sheet or physically walking to the shelf.
     *
     * This directly implements Specific Objective 3 (real-time stock
     * availability lookup) and resolves Problem 3 in the Statement of the
     * Problem: "Delay in Customer Service When Checking Stock Availability."
     */
    public function create()
    {
        $user = Auth::user();

        // For Staff, stock is only relevant for their own branch. For
        // Owner/Manager, include stock for every branch so the branch
        // dropdown can be switched without reloading the page.
        $products = Product::orderBy('name')
            ->with(['inventories' => function ($query) use ($user) {
                if ($user->isStaff()) {
                    $query->where('branch_id', $user->branch_id);
                }
            }])
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    // stockByBranch: { branch_id: quantity, ... }
                    'stockByBranch' => $product->inventories->mapWithKeys(
                        fn ($inv) => [$inv->branch_id => $inv->quantity]
                    ),
                ];
            });

        return view('sales.create', [
            'products' => $products,
            'defaultBranchId' => $user->branch_id,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,gcash,paymaya,card'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        // Staff can only sell from their own branch; Owner/Manager may
        // pass a branch_id to record a sale at any branch — this models
        // the Owner/Manager personally stepping in to cover a branch when
        // its assigned staff member is unavailable (see Chapter 1,
        // Organizational Chart: branch continuity).
        $branchId = $user->isStaff() ? $user->branch_id : $request->input('branch_id', $user->branch_id);

        $sale = DB::transaction(function () use ($data, $branchId, $user) {
            $total = 0;
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                $inventory = Inventory::where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->first();

                if (! $inventory || $inventory->quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$product->name} at this branch.",
                    ]);
                }

                $inventory->decrement('quantity', $item['quantity']);

                $subtotal = $product->price * $item['quantity'];
                $total += $subtotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            $sale = Sale::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'payment_method' => $data['payment_method'],
                'total_amount' => $total,
                'sold_at' => now(),
            ]);

            foreach ($lineItems as $line) {
                SaleItem::create($line + ['sale_id' => $sale->id]);
            }

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('status', 'Sale recorded.');
    }

    public function show(Sale $sale)
    {
        $sale->load(['items.product', 'branch', 'user']);

        return view('sales.show', compact('sale'));
    }

    private function generateInvoiceNumber(): string
    {
        // e.g. INV-20260909-0007
        $today = now()->format('Ymd');
        $countToday = Sale::whereDate('sold_at', now())->count() + 1;

        return sprintf('INV-%s-%04d', $today, $countToday);
    }
}
