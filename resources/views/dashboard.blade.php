@extends('layouts.app')

@section('content')
    <h3 class="mb-4">Dashboard</h3>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Today's Sales</div>
                    <div class="fs-3 fw-bold">₱{{ number_format($todaysSalesTotal, 2) }}</div>
                    <div class="text-muted small">{{ $todaysSalesCount }} transaction(s)</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Low-Stock Items</div>
                    <div class="fs-3 fw-bold text-danger">{{ $lowStockItems->count() }}</div>
                    <div class="text-muted small">need restocking</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <a href="{{ route('sales.create') }}" class="btn btn-success w-100 mb-2">Record a Sale</a>
                    <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary w-100">View Inventory</a>
                </div>
            </div>
        </div>
    </div>

    @if ($lowStockItems->isNotEmpty())
        <div class="card shadow-sm">
            <div class="card-header bg-warning-subtle fw-bold">Low-Stock Alerts</div>
            <ul class="list-group list-group-flush">
                @foreach ($lowStockItems as $item)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $item->product->name }} &mdash; {{ $item->branch->name }} branch</span>
                        <span class="badge bg-danger">{{ $item->quantity }} left</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
