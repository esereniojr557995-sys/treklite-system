@extends('layouts.app')

@section('content')
    <h3 class="mb-3">Invoice {{ $sale->invoice_number }}</h3>

    <div class="card shadow-sm" style="max-width: 600px;">
        <div class="card-body">
            <p class="mb-1"><strong>Branch:</strong> {{ $sale->branch->name }}</p>
            <p class="mb-1"><strong>Processed by:</strong> {{ $sale->user->name }}</p>
            <p class="mb-1"><strong>Date:</strong> {{ $sale->sold_at->format('M d, Y h:i A') }}</p>
            <p class="mb-3"><strong>Payment Method:</strong> <span class="text-capitalize">{{ $sale->payment_method }}</span></p>

            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td>{{ $item->product->name }}</td>
                            <td class="text-end">{{ $item->quantity }}</td>
                            <td class="text-end">₱{{ number_format($item->unit_price, 2) }}</td>
                            <td class="text-end">₱{{ number_format($item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total</th>
                        <th class="text-end">₱{{ number_format($sale->total_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>

            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Back to Sales</a>
        </div>
    </div>
@endsection
