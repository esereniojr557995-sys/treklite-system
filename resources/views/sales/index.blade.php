@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Sales</h3>
        <a href="{{ route('sales.create') }}" class="btn btn-success">+ Record Sale</a>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Branch</th>
                    <th>Processed By</th>
                    <th>Payment</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></td>
                        <td>{{ $sale->sold_at->format('M d, Y h:i A') }}</td>
                        <td>{{ $sale->branch->name }}</td>
                        <td>{{ $sale->user->name }}</td>
                        <td class="text-capitalize">{{ $sale->payment_method }}</td>
                        <td class="text-end">₱{{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No sales recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $sales->links() }}</div>
@endsection
