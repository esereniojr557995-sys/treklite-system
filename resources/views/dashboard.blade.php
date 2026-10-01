@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h3 class="mb-4">Dashboard</h3>

    {{-- 1. SALES SUMMARY --}}
    <h6 class="text-uppercase text-muted mb-2"><i class="bi bi-receipt"></i> Sales</h6>
    <div class="row g-3 mb-4">
        @foreach ([['Today', $today], ['This week', $week], ['This month', $month]] as [$label, $s])
            <div class="col-md-3 col-sm-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="fs-4 fw-bold">₱{{ number_format($s->total, 2) }}</div>
                        <div class="text-muted small">{{ $s->cnt }} transaction(s)</div>
                    </div>
                </div>
            </div>
        @endforeach
        <div class="col-md-3 col-sm-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small mb-1">This month by payment</div>
                    @foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label)
                        <div class="d-flex justify-content-between small">
                            <span>{{ $label }}</span>
                            <span class="fw-semibold">₱{{ number_format($byPayment[$key]->total ?? 0, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 2. INVENTORY SUMMARY --}}
    <h6 class="text-uppercase text-muted mb-2"><i class="bi bi-box-seam"></i> Inventory</h6>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Products</div>
                <div class="fs-4 fw-bold">{{ number_format($productCount) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Units on hand</div>
                <div class="fs-4 fw-bold">{{ number_format($unitsOnHand) }}</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Stock value (at selling price)</div>
                <div class="fs-4 fw-bold">₱{{ number_format($stockValue, 2) }}</div>
            </div></div>
        </div>
    </div>

    {{-- 3. NEEDS ATTENTION --}}
    <h6 class="text-uppercase text-muted mb-2"><i class="bi bi-exclamation-triangle"></i> Needs attention</h6>
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-danger-subtle fw-bold">Out of stock ({{ $outOfStock->count() }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($outOfStock->take(8) as $v)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $v->product->name }} <span class="text-muted">&mdash; {{ $v->label }}</span></span>
                            <span class="badge bg-danger">0</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">Nothing is out of stock.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-warning-subtle fw-bold">Low stock ({{ $lowStock->count() }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($lowStock->take(8) as $v)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $v->product->name }} <span class="text-muted">&mdash; {{ $v->label }}</span></span>
                            <span class="badge bg-warning text-dark">{{ $v->quantity }} left</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No low-stock items.</li>
                    @endforelse
                </ul>
                @if ($outOfStock->count() + $lowStock->count() > 0)
                    <div class="card-footer bg-white text-end">
                        <a href="{{ route('inventory.index') }}" class="small">Go to Inventory to receive stock</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 4. RECENT TRANSACTIONS --}}
    <h6 class="text-uppercase text-muted mb-2"><i class="bi bi-clock-history"></i> Recent transactions</h6>
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-bold">Latest stock received / adjusted</div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentMovements as $m)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>
                                {{ $m->variant->product->name }} <span class="text-muted">({{ $m->variant->label }})</span>
                                <div class="small text-muted">{{ $m->type === 'receive' ? 'Received' : 'Adjusted' }} &middot; {{ $m->created_at->format('M d, h:i A') }}</div>
                            </span>
                            <span class="fw-semibold {{ $m->quantity_change >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No stock movements yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Latest sales</span>
                    <a href="{{ route('sales.create') }}" class="btn btn-success btn-sm">+ New Sale</a>
                </div>
                <ul class="list-group list-group-flush">
                    @forelse ($recentSales as $sale)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>
                                <a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a>
                                <div class="small text-muted">{{ $sale->customer_name }} &middot; {{ $sale->payment_label }} &middot; {{ $sale->sold_at->format('M d, h:i A') }}</div>
                            </span>
                            <span class="fw-semibold">₱{{ number_format($sale->total_amount, 2) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">No sales recorded yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection
