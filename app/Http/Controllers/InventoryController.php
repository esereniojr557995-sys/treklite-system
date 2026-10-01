<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /** Stock list. Everyone can look up availability; only the Owner can change stock. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));

        $variants = ProductVariant::with('product')
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('sku', 'like', "%{$q}%")
                  ->orWhere('size', 'like', "%{$q}%")
                  ->orWhere('color', 'like', "%{$q}%")
                  ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"));
            }))
            ->orderBy('product_id')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.index', compact('variants', 'q'));
    }

    /** Owner records delivered stock (adds to the quantity on hand). */
    public function receive(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);

        $this->move($variant, 'receive', fn ($current) => $current + $data['quantity'], $data['note'] ?? 'Delivery received');

        return back()->with('status', 'Received ' . $data['quantity'] . ' unit(s) of ' . $variant->product->name . ' (' . $variant->label . ').');
    }

    /** Owner sets the quantity to the physically counted number. */
    public function adjust(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'counted' => ['required', 'integer', 'min:0'],
            'note'    => ['nullable', 'string', 'max:255'],
        ]);

        $this->move($variant, 'adjust', fn () => $data['counted'], $data['note'] ?? 'Physical count');

        return back()->with('status', 'Stock of ' . $variant->product->name . ' (' . $variant->label . ') set to ' . $data['counted'] . '.');
    }

    private function move(ProductVariant $variant, string $type, callable $newQty, ?string $note): void
    {
        DB::transaction(function () use ($variant, $type, $newQty, $note) {
            $v      = ProductVariant::lockForUpdate()->findOrFail($variant->id);
            $before = $v->quantity;
            $after  = $newQty($before);

            if ($after === $before) {
                return;
            }

            $v->update(['quantity' => $after]);

            StockMovement::create([
                'variant_id'      => $v->id,
                'user_id'         => auth()->id(),
                'type'            => $type,
                'quantity_change' => $after - $before,
                'quantity_after'  => $after,
                'note'            => $note,
            ]);
        });
    }
}
