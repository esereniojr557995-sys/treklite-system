<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with('user')
            ->when(! auth()->user()->hasFullAccess(), fn ($q) => $q->where('user_id', auth()->id()))
            ->latest('sold_at')
            ->paginate(20);

        return view('sales.index', compact('sales'));
    }

    public function create()
    {
        $products = Product::with('variants')
            ->whereHas('variants')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'       => $p->id,
                'name'     => $p->name,
                'category' => $p->category,
                'brand'    => $p->brand,
                'image'    => $p->image_url,
                'variants' => $p->variants->map(fn ($v) => [
                    'id'    => $v->id,
                    'sku'   => $v->sku,
                    'size'  => $v->size,
                    'color' => $v->color,
                    'label' => $v->label,
                    'price' => (float) ($v->price ?? $p->price),
                    'stock' => (int) $v->quantity,
                ])->values(),
            ])->values();

        $categories = Product::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        // Optional: put your shop's static QR Ph / GCash QR image here to show it at checkout.
        $paymentQr = file_exists(public_path('images/payment-qr.png')) ? asset('images/payment-qr.png') : null;

        return view('sales.create', compact('products', 'categories', 'paymentQr'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_name'      => ['nullable', 'string', 'max:255'],
            'customer_address'   => ['nullable', 'string', 'max:255'],
            'customer_contact'   => ['nullable', 'string', 'max:50'],
            'payment_method'     => ['required', 'in:cash,gcash,paymaya,card'],
            'payment_reference'  => ['nullable', 'required_unless:payment_method,cash', 'string', 'max:100', 'unique:sales,payment_reference'],
            'payment_proof'      => ['nullable', 'image', 'max:4096'],
            'amount_tendered'    => ['nullable', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ], [
            'payment_reference.required_unless' => 'Enter the reference number shown on the customer\'s payment confirmation.',
            'payment_reference.unique'          => 'This reference number was already used on another sale.',
        ]);

        $sale = DB::transaction(function () use ($data, $request) {
            // Merge duplicate variants, then lock them so two sales cannot take the same stock.
            $lines = collect($data['items'])->groupBy('variant_id')->map(fn ($g) => (int) $g->sum('quantity'));

            $variants = ProductVariant::with('product')
                ->whereIn('id', $lines->keys())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $short = [];
            $total = 0.0;
            foreach ($lines as $id => $qty) {
                $v = $variants[$id];
                if ($qty > $v->quantity) {
                    $short[] = $v->product->name . ' (' . $v->label . '): only ' . $v->quantity . ' left';
                }
                $total += $v->selling_price * $qty;
            }
            if ($short) {
                throw ValidationException::withMessages(['items' => ['Not enough stock for: ' . implode('; ', $short)]]);
            }

            $isCash   = $data['payment_method'] === 'cash';
            $tendered = $isCash ? (float) ($data['amount_tendered'] ?? $total) : $total;
            if ($tendered + 0.001 < $total) {
                throw ValidationException::withMessages(['amount_tendered' => ['The amount received is less than the total.']]);
            }

            $prefix = 'INV-' . now()->format('Ymd');
            $seq    = Sale::whereDate('sold_at', today())->lockForUpdate()->count() + 1;

            $sale = Sale::create([
                'invoice_number'     => sprintf('%s-%04d', $prefix, $seq),
                'user_id'            => auth()->id(),
                'customer_name'      => $data['customer_name'] ?: 'Walk-in Customer',
                'customer_address'   => $data['customer_address'] ?? null,
                'customer_contact'   => $data['customer_contact'] ?? null,
                'payment_method'     => $data['payment_method'],
                'payment_reference'  => $isCash ? null : $data['payment_reference'],
                'payment_proof_path' => $request->file('payment_proof')?->store('payment-proofs', 'public'),
                'amount_tendered'    => $tendered,
                'change_amount'      => max(0, $tendered - $total),
                'total_amount'       => $total,
                'sold_at'            => now(),
            ]);

            foreach ($lines as $id => $qty) {
                $v = $variants[$id];
                $sale->items()->create([
                    'variant_id' => $v->id,
                    'quantity'   => $qty,
                    'unit_price' => $v->selling_price,
                    'subtotal'   => $v->selling_price * $qty,
                ]);
                $v->decrement('quantity', $qty);
            }

            return $sale;
        });

        return redirect()->route('sales.show', $sale)->with('status', 'Sale recorded. Invoice ' . $sale->invoice_number . ' is ready to print.');
    }

    public function show(Sale $sale)
    {
        abort_unless(
            auth()->user()->hasFullAccess() || $sale->user_id === auth()->id(),
            403
        );

        $sale->load('items.variant.product', 'user');

        return view('sales.show', compact('sale'));
    }
}
