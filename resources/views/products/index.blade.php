@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-1">
        <h3 class="mb-0"></h3>
        <a href="{{ route('products.create') }}" class="btn btn-success">+ Add Product</a>
    </div>
    <p class="text-muted small mb-3">
        Products are the items you sell (name, brand, category, price, sizes and colors).
        Add the product here first, then record its stock under <a href="{{ route('inventory.index') }}">Inventory</a>.
    </p>

    {{-- Filters --}}
    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-1">Search</label>
                    <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Model, brand or category">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small mb-1">Brand</label>
                    <select name="brand" class="form-select form-select-sm">
                        <option value="">All brands</option>
                        @foreach ($brands as $b)
                            <option value="{{ $b }}" @selected($brand === $b)>{{ $b }}</option>
                        @endforeach
                        <option value="_none" @selected($brand === '_none')>No brand</option>
                    </select>
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-sm btn-success flex-fill">Filter</button>
                    @if ($q !== '' || $brand !== '' || $category !== '')
                        <a href="{{ route('products.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:64px"></th>
                        <th>Model</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Variants</th>
                        <th class="text-end" style="min-width: 230px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $p)
                        <tr>
                            <td>
                                @if ($p->image_url)
                                    <img src="{{ $p->image_url }}" alt="" width="48" height="48" class="rounded" style="object-fit:cover">
                                @else
                                    <div class="rounded bg-light text-secondary d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="bi bi-image"></i></div>
                                @endif
                            </td>
                            <td class="fw-semibold">{{ $p->name }}</td>
                            <td>
                                @if ($p->brand)
                                    <span class="badge text-bg-dark">{{ $p->brand }}</span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>
                            <td>
                                @if ($p->category)
                                    <span class="badge text-bg-secondary">{{ $p->category }}</span>
                                @else
                                    <span class="text-muted small">&mdash;</span>
                                @endif
                            </td>
                            <td class="text-end">₱{{ number_format($p->price, 2) }}</td>
                            <td class="text-center">{{ $p->variants->count() }}</td>
                            <td class="text-end">
                                <a href="{{ route('inventory.index', ['product' => $p->id]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-box-seam"></i> Manage Inventory</a>
                                <a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">
                            @if ($q !== '' || $brand !== '' || $category !== '')
                                No products match the filters.
                            @else
                                No products yet. Click <strong>+ Add Product</strong> to create the first one.
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
@endsection
