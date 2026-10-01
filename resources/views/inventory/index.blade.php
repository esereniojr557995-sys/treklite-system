@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
    @php $isOwner = auth()->user()->hasFullAccess(); @endphp

    <style>
        .stock-form { display: flex; gap: .25rem; justify-content: flex-end; }
        .stock-form + .stock-form { margin-top: .25rem; }
        .stock-input { width: 90px; flex: 0 0 90px; }
        .stock-btn { width: 105px; flex: 0 0 105px; }
        .status-badge { display: inline-block; width: 90px; }
        .thumb { width: 40px; height: 40px; flex-shrink: 0; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h3 class="mb-0">Inventory</h3>
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search product, SKU, size, color...">
            <button class="btn btn-outline-secondary">Search</button>
        </form>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:56px"></th>
                    <th>Product</th>
                    <th>Size / Color</th>
                    <th>SKU</th>
                    <th class="text-end" style="width:100px">Quantity</th>
                    <th class="text-center" style="width:130px">Status</th>
                    @if ($isOwner)
                        <th class="text-end" style="width:240px">Receive stock / Count</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($variants as $v)
                    <tr class="{{ $v->isOutOfStock() || $v->isLowStock() ? 'table-danger' : '' }}">
                        <td>
                            @if ($v->product->image_url)
                                <img src="{{ $v->product->image_url }}" alt="" class="rounded thumb" style="object-fit:cover">
                            @else
                                <div class="rounded bg-light text-secondary d-flex align-items-center justify-content-center thumb"><i class="bi bi-image"></i></div>
                            @endif
                        </td>
                        <td>{{ $v->product->name }}</td>
                        <td>{{ $v->label }}</td>
                        <td class="text-muted small">{{ $v->sku }}</td>
                        <td class="text-end">{{ $v->quantity }}</td>
                        <td class="text-center">
                            @if ($v->isOutOfStock())
                                <span class="badge bg-danger status-badge">Out of stock</span>
                            @elseif ($v->isLowStock())
                                <span class="badge bg-warning text-dark status-badge">Low stock</span>
                            @else
                                <span class="badge bg-success status-badge">OK</span>
                            @endif
                        </td>
                        @if ($isOwner)
                            <td>
                                <form method="POST" action="{{ route('inventory.receive', $v) }}" class="stock-form">
                                    @csrf
                                    <input type="number" name="quantity" min="1" class="form-control form-control-sm stock-input" placeholder="Qty" required>
                                    <button class="btn btn-sm btn-outline-success stock-btn">Receive</button>
                                </form>
                                <form method="POST" action="{{ route('inventory.adjust', $v) }}" class="stock-form">
                                    @csrf
                                    <input type="number" name="counted" min="0" class="form-control form-control-sm stock-input" placeholder="Count" required>
                                    <button class="btn btn-sm btn-outline-secondary stock-btn">Set count</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No inventory records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $variants->links() }}</div>
@endsection