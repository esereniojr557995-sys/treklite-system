@extends('layouts.app')

@section('title', 'Sales Records')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"></h3>
        <a href="{{ route('sales.create') }}" class="btn btn-success">+ New Sale</a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small mb-1">Search</label>
                    <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Invoice no., customer name or contact">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label small mb-1">Cashier</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All cashiers</option>
                        @foreach ($cashiers as $c)
                            <option value="{{ $c->id }}" @selected((string) $userId === (string) $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-4 col-6">
                    <label class="form-label small mb-1">Payment</label>
                    <select name="payment" class="form-select form-select-sm">
                        <option value="">All payments</option>
                        @foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label)
                            <option value="{{ $key }}" @selected($payment === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-4 d-flex gap-1">
                    <button class="btn btn-sm btn-success flex-fill">Filter</button>
                    @if ($q !== '' || $from !== '' || $to !== '' || $userId !== '' || $payment !== '')
                        <a href="{{ route('sales.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="small text-muted mb-2">
        {{ number_format($count) }} transaction(s) &middot; total <strong>₱{{ number_format($total, 2) }}</strong>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Cashier</th>
                        <th>Payment</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a></td>
                            <td>{{ $sale->sold_at->format('M d, Y h:i A') }}</td>
                            <td>{{ $sale->customer_name }}</td>
                            <td>{{ $sale->user->name }}</td>
                            <td>
                                {{ $sale->payment_label }}
                                @if ($sale->payment_proof_path)
                                    <i class="bi bi-image text-muted" title="Payment photo attached"></i>
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format($sale->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No sales match the filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $sales->links() }}</div>
@endsection
