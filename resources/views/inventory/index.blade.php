@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
    @php
        $isOwner  = auth()->user()->hasFullAccess();
        $f        = $filters;
        $anyFilter = $f['q'] !== '' || $f['category'] !== '' || $f['size'] !== '' || $f['color'] !== '' || $f['status'] !== '';
        $brandLabel = fn ($b) => $b === '_none' ? 'No brand' : $b;
    @endphp

    {{-- Breadcrumb --}}
    <nav class="small mb-3">
        <a href="{{ route('inventory.index') }}">All brands</a>
        @if ($mode === 'product')
            &rsaquo; <a href="{{ route('inventory.index', ['brand' => $product->brand ?: '_none']) }}">{{ $product->brand ?: 'No brand' }}</a>
            &rsaquo; <strong>{{ $product->name }}</strong>
        @elseif ($mode === 'brand')
            &rsaquo; <strong>{{ $brandLabel($f['brand']) }}</strong>
        @elseif ($mode === 'flat')
            &rsaquo; <strong>Search results</strong>
        @endif
    </nav>

    {{-- Filters (the same bar on every level) --}}
    <form method="GET" class="card shadow-sm mb-3">
        <div class="card-body">
            @if ($mode === 'product') <input type="hidden" name="product" value="{{ $product->id }}"> @endif
            @if ($mode === 'brand')   <input type="hidden" name="brand" value="{{ $f['brand'] }}"> @endif
            <div class="row g-2 align-items-end">
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small mb-1">Search</label>
                    <input type="search" name="q" value="{{ $f['q'] }}" class="form-control form-control-sm" placeholder="Model, brand, size or color">
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c }}" @selected($f['category'] === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1">Size</label>
                    <select name="size" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($sizes as $s)
                            <option value="{{ $s }}" @selected($f['size'] === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1">Color</label>
                    <select name="color" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach ($colors as $c)
                            <option value="{{ $c }}" @selected($f['color'] === $c)>{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1">Stock level</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="low" @selected($f['status'] === 'low')>Low stock</option>
                        <option value="out" @selected($f['status'] === 'out')>Out of stock</option>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3 d-flex gap-1">
                    <button class="btn btn-sm btn-success flex-fill">Go</button>
                    @if ($anyFilter)
                        <a href="{{ route('inventory.index', array_filter(['product' => $mode === 'product' ? $product->id : null, 'brand' => $mode === 'brand' ? $f['brand'] : null])) }}" class="btn btn-sm btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
                    @endif
                </div>
            </div>
        </div>
    </form>

    {{-- ===================== LEVEL 1: BRANDS ===================== --}}
    @if ($mode === 'brands')
        @if ($brandRows->isEmpty())
            <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
                @if (! $hasProducts)
                    <i class="bi bi-box-seam fs-1 d-block mb-2"></i>
                    There are no products yet, so there is no stock to show.
                    @if ($isOwner)
                        <div class="mt-2">Add the product first, then come back here to receive its stock.</div>
                        <a href="{{ route('products.create') }}" class="btn btn-success mt-3">+ Add Product</a>
                    @else
                        <div>The Owner needs to add the products first.</div>
                    @endif
                @else
                    No brands match the filter.
                @endif
            </div></div>
        @else
            <div class="row g-3">
                @foreach ($brandRows as $b)
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <a href="{{ route('inventory.index', ['brand' => $b['key']]) }}" class="text-decoration-none text-body">
                            <div class="card shadow-sm h-100">
                                <div class="card-body d-flex gap-3 align-items-center">
                                    @if ($b['image'])
                                        <img src="{{ $b['image'] }}" alt="" width="56" height="56" class="rounded" style="object-fit:cover">
                                    @else
                                        <div class="rounded bg-light text-secondary d-flex align-items-center justify-content-center flex-shrink-0" style="width:56px;height:56px"><i class="bi bi-tag fs-4"></i></div>
                                    @endif
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">{{ $b['name'] }}</div>
                                        <div class="small text-muted">{{ $b['models'] }} model(s) &middot; {{ number_format($b['units']) }} unit(s)</div>
                                        <div class="mt-1">
                                            @if ($b['out'] > 0) <span class="badge bg-danger">{{ $b['out'] }} out</span> @endif
                                            @if ($b['low'] > 0) <span class="badge bg-warning text-dark">{{ $b['low'] }} low</span> @endif
                                            @if ($b['out'] === 0 && $b['low'] === 0) <span class="badge bg-success">OK</span> @endif
                                        </div>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted"></i>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ===================== LEVEL 2: MODELS OF A BRAND ===================== --}}
    @if ($mode === 'brand')
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr><th style="width:56px"></th><th>Model</th><th>Category</th><th class="text-center">Variants</th><th class="text-end">Units in stock</th><th class="text-center">Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $p)
                            @php
                                $out = $p->variants->filter(fn ($v) => $v->isOutOfStock())->count();
                                $low = $p->variants->filter(fn ($v) => $v->isLowStock())->count();
                            @endphp
                            <tr>
                                <td>
                                    @if ($p->image_url)
                                        <img src="{{ $p->image_url }}" alt="" width="40" height="40" class="rounded" style="object-fit:cover">
                                    @else
                                        <div class="rounded bg-light text-secondary d-flex align-items-center justify-content-center" style="width:40px;height:40px"><i class="bi bi-image"></i></div>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $p->name }}</td>
                                <td>{{ $p->category }}</td>
                                <td class="text-center">{{ $p->variants->count() }}</td>
                                <td class="text-end">{{ $p->total_stock }}</td>
                                <td class="text-center">
                                    @if ($out > 0) <span class="badge bg-danger">{{ $out }} out</span> @endif
                                    @if ($low > 0) <span class="badge bg-warning text-dark">{{ $low }} low</span> @endif
                                    @if ($out === 0 && $low === 0) <span class="badge bg-success">OK</span> @endif
                                </td>
                                <td class="text-end"><a href="{{ route('inventory.index', ['product' => $p->id]) }}" class="btn btn-sm btn-outline-success">View sizes / colors</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No models for this brand.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== LEVEL 3: ONE MODEL ===================== --}}
    @if ($mode === 'product')
        <div class="d-flex gap-3 align-items-center mb-3">
            @if ($product->image_url)
                <img src="{{ $product->image_url }}" alt="" width="64" height="64" class="rounded" style="object-fit:cover">
            @endif
            <div>
                <h5 class="mb-0">{{ $product->name }}</h5>
                <div class="small text-muted">{{ $product->brand ?: 'No brand' }}@if ($product->category) &middot; {{ $product->category }}@endif &middot; {{ $product->total_stock }} unit(s) in stock</div>
            </div>
            @if ($isOwner)
                <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-secondary ms-auto">Edit product</a>
            @endif
        </div>

        @include('inventory._variants', ['variants' => $variants, 'showProduct' => false])

        <h6 class="mt-4 mb-2">Stock history</h6>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr><th>Date</th><th>Size / Color</th><th>Action</th><th class="text-end">Before</th><th class="text-end">Change</th><th class="text-end">After</th><th>By</th><th>Note</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $m)
                            @php $before = $m->quantity_before ?? ($m->quantity_after - $m->quantity_change); @endphp
                            <tr>
                                <td class="small">{{ $m->created_at->format('M d, Y h:i A') }}</td>
                                <td>{{ $m->variant->label }}</td>
                                <td><span class="badge {{ $m->type === 'receive' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $m->type_label }}</span></td>
                                <td class="text-end">{{ $before }}</td>
                                <td class="text-end fw-semibold {{ $m->quantity_change < 0 ? 'text-danger' : ($m->quantity_change > 0 ? 'text-success' : 'text-muted') }}">{{ $m->quantity_change > 0 ? '+' : '' }}{{ $m->quantity_change }}</td>
                                <td class="text-end">{{ $m->quantity_after }}</td>
                                <td class="small">{{ $m->user?->name }}</td>
                                <td class="small text-muted">{{ $m->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-3">No stock movements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-2">{{ $history->links() }}</div>
    @endif

    {{-- ===================== FLAT SEARCH RESULTS ===================== --}}
    @if ($mode === 'flat')
        @include('inventory._variants', ['variants' => $variants, 'showProduct' => true])
        <div class="mt-3">{{ $variants->links() }}</div>
    @endif
@endsection
