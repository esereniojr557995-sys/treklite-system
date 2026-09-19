<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Product catalog management — Owner/Co-Owner only (see routes/web.php).
 * Creating a product also seeds a zero-stock inventory row per branch,
 * so every product is immediately visible/trackable across branches.
 */
class ProductController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('name')->paginate(15);

        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $product = Product::create($data);

        // Initialize a stock row (qty 0) for every existing branch.
        foreach (Branch::all() as $branch) {
            Inventory::firstOrCreate(
                ['product_id' => $product->id, 'branch_id' => $branch->id],
                ['quantity' => 0]
            );
        }

        return redirect()->route('products.index')->with('status', 'Product added.');
    }
}
