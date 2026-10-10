<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryController extends Controller
{
    /**
     * Stock view. Everyone can look up availability; only the Owner can change stock.
     *
     *   /inventory                  -> brands (one row per brand, so the main view stays short)
     *   /inventory?brand=Treklite   -> the models of that brand
     *   /inventory?product=12       -> the sizes / colors (variants) of one model, with stock actions and history
     *   any search / size / color   -> a flat list of matching variants
     */
    public function index(Request $request)
    {
        $q        = trim((string) $request->query('q'));
        $category = (string) $request->query('category');
        $size     = (string) $request->query('size');
        $color    = (string) $request->query('color');
        $brand    = (string) $request->query('brand');
        $status   = (string) $request->query('status');     // low | out
        $productId = $request->query('product');

        $filters = compact('q', 'category', 'size', 'color', 'brand', 'status');

        $data = [
            'filters'    => $filters,
            'categories' => Product::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'sizes'      => ProductVariant::whereNotNull('size')->where('size', '!=', '')->distinct()->orderBy('size')->pluck('size'),
            'colors'     => ProductVariant::whereNotNull('color')->where('color', '!=', '')->distinct()->orderBy('color')->pluck('color'),
            'brands'     => Product::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
            'hasProducts' => Product::exists(),
        ];

        // ---- Level 3: one model ----
        if ($productId) {
            $product = Product::findOrFail($productId);

            $variants = $product->variants()
                ->when($q !== '', fn ($v) => $v->where(fn ($w) => $w->where('size', 'like', "%{$q}%")->orWhere('color', 'like', "%{$q}%")))
                ->when($size !== '', fn ($v) => $v->where('size', $size))
                ->when($color !== '', fn ($v) => $v->where('color', $color))
                ->when($status === 'out', fn ($v) => $v->where('quantity', '<=', 0))
                ->when($status === 'low', fn ($v) => $v->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'low_stock_threshold'))
                ->orderBy('id')
                ->get()
                ->each(fn ($v) => $v->setRelation('product', $product));

            $history = StockMovement::with('variant', 'user')
                ->whereIn('variant_id', $product->variants()->pluck('id'))
                ->latest()->latest('id')
                ->paginate(10, ['*'], 'history')
                ->withQueryString();

            return view('inventory.index', $data + ['mode' => 'product', 'product' => $product, 'variants' => $variants, 'history' => $history]);
        }

        // ---- Flat search over variants (any search / size / color / status) ----
        if ($q !== '' || $size !== '' || $color !== '' || $status !== '') {
            $variants = $this->variantQuery($filters)
                ->with('product')
                ->orderBy('product_id')->orderBy('id')
                ->paginate(25)
                ->withQueryString();

            return view('inventory.index', $data + ['mode' => 'flat', 'variants' => $variants]);
        }

        // ---- Level 2: models of one brand ----
        if ($brand !== '') {
            $products = Product::with('variants')
                ->when($brand === '_none', fn ($p) => $p->where(fn ($w) => $w->whereNull('brand')->orWhere('brand', '')), fn ($p) => $p->where('brand', $brand))
                ->when($category !== '', fn ($p) => $p->where('category', $category))
                ->orderBy('name')
                ->get();

            return view('inventory.index', $data + ['mode' => 'brand', 'products' => $products]);
        }

        // ---- Level 1: brands ----
        $brandRows = Product::with('variants')
            ->when($category !== '', fn ($p) => $p->where('category', $category))
            ->get()
            ->groupBy(fn ($p) => $p->brand ?: '_none')
            ->map(function ($group, $key) {
                $variants = $group->flatMap->variants;

                return [
                    'key'    => $key,
                    'name'   => $key === '_none' ? 'No brand' : $key,
                    'models' => $group->count(),
                    'units'  => (int) $variants->sum('quantity'),
                    'low'    => $variants->filter(fn ($v) => $v->isLowStock())->count(),
                    'out'    => $variants->filter(fn ($v) => $v->isOutOfStock())->count(),
                    'image'  => optional($group->first(fn ($p) => $p->image_path))->image_url,
                ];
            })
            ->sortBy(fn ($r) => $r['key'] === '_none' ? 'zzz' : strtolower($r['name']))
            ->values();

        return view('inventory.index', $data + ['mode' => 'brands', 'brandRows' => $brandRows]);
    }

    private function variantQuery(array $f)
    {
        return ProductVariant::query()
            ->when($f['q'] !== '', fn ($v) => $v->where(function ($w) use ($f) {
                $q = $f['q'];
                $w->where('size', 'like', "%{$q}%")
                  ->orWhere('color', 'like', "%{$q}%")
                  ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"));
            }))
            ->when($f['size'] !== '', fn ($v) => $v->where('size', $f['size']))
            ->when($f['color'] !== '', fn ($v) => $v->where('color', $f['color']))
            ->when($f['status'] === 'out', fn ($v) => $v->where('quantity', '<=', 0))
            ->when($f['status'] === 'low', fn ($v) => $v->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'low_stock_threshold'))
            ->when($f['category'] !== '', fn ($v) => $v->whereHas('product', fn ($p) => $p->where('category', $f['category'])))
            ->when($f['brand'] === '_none', fn ($v) => $v->whereHas('product', fn ($p) => $p->whereNull('brand')->orWhere('brand', '')))
            ->when($f['brand'] !== '' && $f['brand'] !== '_none', fn ($v) => $v->whereHas('product', fn ($p) => $p->where('brand', $f['brand'])));
    }

    /**
     * Owner records a delivery (Receive) or a physical count (Count).
     *  - Receive adds the entered number to the quantity on hand.
     *  - Count sets the quantity to the counted number. The system quantity before the count and the
     *    difference are saved in the history, so nothing is silently overwritten.
     */
    public function update(Request $request, ProductVariant $variant)
    {
        $data = $request->validate([
            'action'   => ['required', 'in:receive,count'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note'     => ['nullable', 'string', 'max:255'],
        ], [
            'quantity.required' => 'Enter the number received or counted.',
        ]);

        if ($data['action'] === 'receive' && $data['quantity'] < 1) {
            throw ValidationException::withMessages(['quantity' => 'The quantity received must be at least 1.']);
        }

        $message = DB::transaction(function () use ($variant, $data) {
            $v      = ProductVariant::with('product')->lockForUpdate()->findOrFail($variant->id);
            $before = (int) $v->quantity;
            $qty    = (int) $data['quantity'];
            $name   = $v->product->name . ' (' . $v->label . ')';

            if ($data['action'] === 'receive') {
                $after = $before + $qty;
                $note  = $data['note'] ?: 'Delivery received';
            } else {
                $after = $qty;
                $diff  = $after - $before;
                if ($diff !== 0 && blank($data['note'] ?? null)) {
                    throw ValidationException::withMessages([
                        'note' => "The count ($after) differs from the system quantity ($before). Enter the reason in the note.",
                    ]);
                }
                $note = $data['note'] ?: 'Physical count matched';
            }

            $v->update(['quantity' => $after]);

            $m = StockMovement::create([
                'variant_id'      => $v->id,
                'user_id'         => auth()->id(),
                'type'            => $data['action'],
                'quantity_before' => $before,
                'quantity_change' => $after - $before,
                'quantity_after'  => $after,
                'note'            => $note,
            ]);

            return $name . ': ' . $m->summary . '.';
        });

        return back()->with('status', $message);
    }
}
