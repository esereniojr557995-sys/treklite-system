<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('variants')->orderBy('name')->paginate(20);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.form', ['product' => new Product()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            $product = Product::create($this->productFields($request, $data));
            $this->saveVariants($product, $data['variants']);
        });

        return redirect()->route('products.index')->with('status', 'Product added.');
    }

    public function edit(Product $product)
    {
        $product->load('variants');

        return view('products.form', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data, $product) {
            $fields = $this->productFields($request, $data);
            if (isset($fields['image_path']) && $product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $product->update($fields);
            $this->saveVariants($product, $data['variants']);
        });

        return redirect()->route('products.index')->with('status', 'Product updated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'                         => ['required', 'string', 'max:255'],
            'category'                     => ['nullable', 'string', 'max:255'],
            'brand'                        => ['nullable', 'string', 'max:255'],
            'description'                  => ['nullable', 'string', 'max:2000'],
            'price'                        => ['required', 'numeric', 'min:0'],
            'image'                        => ['nullable', 'image', 'max:4096'],
            'variants'                     => ['required', 'array', 'min:1'],
            'variants.*.id'                => ['nullable', 'integer'],
            'variants.*.sku'               => ['required', 'string', 'max:255'],
            'variants.*.size'              => ['nullable', 'string', 'max:50'],
            'variants.*.color'             => ['nullable', 'string', 'max:50'],
            'variants.*.price'             => ['nullable', 'numeric', 'min:0'],
            'variants.*.low_stock_threshold' => ['required', 'integer', 'min:0'],
            'variants.*.quantity'          => ['nullable', 'integer', 'min:0'],   // opening stock, new variants only
        ]);
    }

    private function productFields(Request $request, array $data): array
    {
        $fields = collect($data)->only(['name', 'category', 'brand', 'description', 'price'])->all();

        if ($request->hasFile('image')) {
            $fields['image_path'] = $request->file('image')->store('products', 'public');
        }

        return $fields;
    }

    private function saveVariants(Product $product, array $rows): void
    {
        foreach ($rows as $i => $row) {
            $id = $row['id'] ?? null;

            $skuTaken = ProductVariant::where('sku', $row['sku'])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists();
            if ($skuTaken) {
                throw ValidationException::withMessages(["variants.$i.sku" => 'The SKU ' . $row['sku'] . ' is already used.']);
            }

            $attrs = [
                'sku'                 => $row['sku'],
                'size'                => $row['size'] ?? null,
                'color'               => $row['color'] ?? null,
                'price'               => ($row['price'] ?? '') === '' ? null : $row['price'],
                'low_stock_threshold' => $row['low_stock_threshold'],
            ];

            if ($id) {
                // Quantity is never edited here; use Inventory > Receive / Adjust so every change is logged.
                $product->variants()->whereKey($id)->update($attrs);
            } else {
                $opening = (int) ($row['quantity'] ?? 0);
                $variant = $product->variants()->create($attrs + ['quantity' => $opening]);

                if ($opening > 0) {
                    StockMovement::create([
                        'variant_id'      => $variant->id,
                        'user_id'         => auth()->id(),
                        'type'            => 'receive',
                        'quantity_change' => $opening,
                        'quantity_after'  => $opening,
                        'note'            => 'Opening stock',
                    ]);
                }
            }
        }
    }
}
