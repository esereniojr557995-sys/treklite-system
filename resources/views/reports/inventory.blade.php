@extends('layouts.app')

@section('title', 'Inventory Report')

@section('content')
    <style>
        @media print {
            .sidebar, .topbar, .no-print, .sidebar-backdrop { display: none !important; }
            .main-wrapper { margin-left: 0 !important; }
            body { background: #fff !important; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Inventory Report</h3>
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer"></i> Print</button>
    </div>

    {{-- Report tabs --}}
    <ul class="nav nav-tabs mb-3 no-print">
        <li class="nav-item"><a class="nav-link" href="{{ route('reports.sales') }}">Sales Report</a></li>
        <li class="nav-item"><a class="nav-link active" href="{{ route('reports.inventory') }}">Inventory Report</a></li>
    </ul>

    <form method="GET" class="mb-3 no-print">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" name="attention" value="1" id="attention"
                   @checked($onlyAttention) onchange="this.form.submit()">
            <label class="form-check-label" for="attention">Show only low-stock and out-of-stock items</label>
        </div>
    </form>

    <div class="small text-muted mb-2">As of {{ now()->format('M d, Y h:i A') }}</div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Units on hand</div><div class="fs-4 fw-bold">{{ number_format($totalUnits) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Stock value</div><div class="fs-4 fw-bold">₱{{ number_format($totalValue, 2) }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Low stock</div><div class="fs-4 fw-bold text-warning">{{ $lowCount }}</div></div></div></div>
        <div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Out of stock</div><div class="fs-4 fw-bold text-danger">{{ $outCount }}</div></div></div></div>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Size / Color</th>
                    <th>SKU</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-end">Low at</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Unit price</th>
                    <th class="text-end">Stock value</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($variants as $v)
                    <tr>
                        <td>{{ $v->product->name }}</td>
                        <td>{{ $v->label }}</td>
                        <td class="small text-muted">{{ $v->sku }}</td>
                        <td class="text-end">{{ $v->quantity }}</td>
                        <td class="text-end text-muted">{{ $v->low_stock_threshold }}</td>
                        <td class="text-center">
                            @if ($v->isOutOfStock())
                                <span class="badge bg-danger">Out of stock</span>
                            @elseif ($v->isLowStock())
                                <span class="badge bg-warning text-dark">Low stock</span>
                            @else
                                <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                        <td class="text-end">₱{{ number_format($v->selling_price, 2) }}</td>
                        <td class="text-end">₱{{ number_format($v->quantity * $v->selling_price, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Nothing to show.</td></tr>
                @endforelse
            </tbody>
            @if ($variants->isNotEmpty())
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end">Total</td>
                        <td class="text-end">{{ number_format($totalUnits) }}</td>
                        <td colspan="3"></td>
                        <td class="text-end">₱{{ number_format($totalValue, 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
