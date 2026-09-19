@extends('layouts.app')

@section('content')
    <h3 class="mb-3">Sales Report</h3>

    <form method="GET" class="row g-2 mb-4">
        <div class="col-auto">
            <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}">
        </div>
        <div class="col-auto">
            <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}">
        </div>
        <div class="col-auto">
            <button class="btn btn-success">Filter</button>
        </div>
    </form>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <strong>Total Revenue:</strong> ₱{{ number_format($totalRevenue, 2) }}
            &nbsp;|&nbsp; <strong>Transactions:</strong> {{ $sales->count() }}
        </div>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Branch</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->sold_at->format('M d, Y h:i A') }}</td>
                        <td>{{ $sale->branch->name }}</td>
                        <td class="text-end">₱{{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No sales in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
