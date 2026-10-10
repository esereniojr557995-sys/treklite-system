<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /** Sales Records with search, date range, cashier and payment filters. */
    public function index(Request $request)
    {
        $q       = trim((string) $request->query('q'));
        $from    = (string) $request->query('from');
        $to      = (string) $request->query('to');
        $userId  = (string) $request->query('user_id');
        $payment = (string) $request->query('payment');

        $query = Sale::query()
            ->when($q !== '', fn ($s) => $s->where(fn ($w) => $w
                ->where('invoice_number', 'like', "%{$q}%")
                ->orWhere('customer_name', 'like', "%{$q}%")
                ->orWhere('customer_contact', 'like', "%{$q}%")))
            ->when($from !== '', fn ($s) => $s->whereDate('sold_at', '>=', $from))
            ->when($to !== '', fn ($s) => $s->whereDate('sold_at', '<=', $to))
            ->when($userId !== '', fn ($s) => $s->where('user_id', $userId))
            ->when($payment !== '', fn ($s) => $s->where('payment_method', $payment));

        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('total_amount');

        $sales    = $query->with('user')->latest('sold_at')->paginate(20)->withQueryString();
        $cashiers = User::orderBy('name')->get(['id', 'name']);

        return view('sales.index', compact('sales', 'cashiers', 'q', 'from', 'to', 'userId', 'payment', 'count', 'total'));
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
            'payment_method'     => ['required', 'in:cash,gcash,bank_transfer'],
            'payment_proof'      => ['nullable', 'required_unless:payment_method,cash', 'image', 'max:4096'],
            'amount_tendered'    => ['nullable', 'required_if:payment_method,cash', 'numeric', 'min:0'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ], [
            'payment_proof.required_unless' => 'Attach a photo of the payment confirmation (it must show the amount paid).',
            'amount_tendered.required_if'   => 'Enter the amount received from the customer.',
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
            // Cash: the amount received must cover the total (more is fine, the change is returned).
            // GCash / bank transfer: the exact total is paid and the photo of the confirmation is the proof.
            $tendered = $isCash ? (float) $data['amount_tendered'] : $total;
            if ($tendered + 0.001 < $total) {
                throw ValidationException::withMessages(['amount_tendered' => ['The amount received (₱' . number_format($tendered, 2) . ') is less than the total (₱' . number_format($total, 2) . '). The sale was not processed.']]);
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
                'payment_reference'  => null,
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
        $sale->load('items.variant.product', 'user');

        return view('sales.show', compact('sale'));
    }
}
