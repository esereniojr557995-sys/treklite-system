@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Products</h3>
        <a href="{{ route('products.create') }}" class="btn btn-success">+ Add Product</a>
    </div>

    <div class="card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:64px"></th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Brand</th>
                    <th class="text-end">Price</th>
                    <th class="text-center">Variants</th>
                    <th class="text-end">Total Stock</th>
                    <th></th>
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
                        <td>{{ $p->category }}</td>
                        <td>{{ $p->brand }}</td>
                        <td class="text-end">₱{{ number_format($p->price, 2) }}</td>
                        <td class="text-center">{{ $p->variants->count() }}</td>
                        <td class="text-end">{{ $p->total_stock }}</td>
                        <td class="text-end"><a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-outline-secondary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>
@endsection
