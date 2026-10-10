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
    public function index(Request $request)
    {
        $q        = trim((string) $request->query('q'));
        $category = (string) $request->query('category');
        $brand    = (string) $request->query('brand');

        $products = Product::with('variants')
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('brand', 'like', "%{$q}%")
                  ->orWhere('category', 'like', "%{$q}%");
            }))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($brand === '_none', fn ($query) => $query->where(fn ($w) => $w->whereNull('brand')->orWhere('brand', '')))
            ->when($brand !== '' && $brand !== '_none', fn ($query) => $query->where('brand', $brand))
            ->orderBy('brand')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $categories = Product::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category');
        $brands     = Product::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand');

        return view('products.index', compact('products', 'q', 'category', 'brand', 'categories', 'brands'));
    }

    public function create()
    {
        return view('products.form', ['product' => new Product()] + $this->suggestions());
    }

    /** Existing categories and brands, offered as suggestions so the same name is not typed two ways. */
    private function suggestions(): array
    {
        return [
            'categories' => Product::whereNotNull('category')->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'brands'     => Product::whereNotNull('brand')->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand'),
        ];
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

        return view('products.form', compact('product') + $this->suggestions());
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
        // The same size + color cannot be entered twice for one product.
        $seen = [];
        foreach ($rows as $i => $row) {
            $key = mb_strtolower(trim(($row['size'] ?? '') . '|' . ($row['color'] ?? '')));
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["variants.$i.size" => 'This size / color is entered twice. Combine them into one row.']);
            }
            $seen[$key] = true;
        }

        foreach ($rows as $i => $row) {
            $id = $row['id'] ?? null;

            $attrs = [
                'size'                => ($row['size'] ?? '') === '' ? null : $row['size'],
                'color'               => ($row['color'] ?? '') === '' ? null : $row['color'],
                'price'               => ($row['price'] ?? '') === '' ? null : $row['price'],
                'low_stock_threshold' => $row['low_stock_threshold'],
            ];

            if ($id) {
                // Quantity is never edited here; use Inventory > Receive / Adjust so every change is logged.
                $product->variants()->whereKey($id)->update($attrs);
            } else {
                $opening = (int) ($row['quantity'] ?? 0);
                $variant = $product->variants()->create($attrs + ['sku' => $this->makeSku($product, $attrs), 'quantity' => $opening]);

                if ($opening > 0) {
                    StockMovement::create([
                        'variant_id'      => $variant->id,
                        'user_id'         => auth()->id(),
                        'type'            => 'receive',
                        'quantity_before' => 0,
                        'quantity_change' => $opening,
                        'quantity_after'  => $opening,
                        'note'            => 'Opening stock',
                    ]);
                }
            }
        }
    }

    /** The system keeps an internal code for each variant (staff never type it). */
    private function makeSku(Product $product, array $attrs): string
    {
        $base = strtoupper(\Illuminate\Support\Str::limit(\Illuminate\Support\Str::slug($product->name, ''), 8, ''))
            . '-' . strtoupper(\Illuminate\Support\Str::slug(($attrs['size'] ?? '') . ($attrs['color'] ?? ''), ''));
        $base = rtrim($base, '-') ?: 'ITEM';

        $sku = $base;
        $n   = 2;
        while (ProductVariant::where('sku', $sku)->exists()) {
            $sku = $base . '-' . $n++;
        }

        return $sku;
    }
}
