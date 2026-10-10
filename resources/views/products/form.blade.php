@extends('layouts.app')

@php $editing = $product->exists; @endphp

@section('title', $editing ? 'Edit Product' : 'Add Product')

@section('content')
    <h3 class="mb-3"></h3>

    <form method="POST"
          action="{{ $editing ? route('products.update', $product) : route('products.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="row g-3">
            {{-- Product details --}}
            <div class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header fw-bold">Product details</div>
                    <div class="card-body">
                        <div class="mb-3 text-center">
                            @if ($product->image_url)
                                <img src="{{ $product->image_url }}" id="photo-preview" class="img-fluid rounded mb-2" style="max-height: 200px" alt="">
                            @else
                                <div id="photo-placeholder" class="rounded bg-light text-secondary d-flex align-items-center justify-content-center mb-2 mx-auto" style="width:200px;height:200px;font-size:3rem"><i class="bi bi-image"></i></div>
                                <img src="" id="photo-preview" class="img-fluid rounded mb-2 d-none" style="max-height: 200px" alt="">
                            @endif
                            <input type="file" name="image" id="photo-input" accept="image/*" class="form-control form-control-sm">
                            <div class="form-text">Product photo (JPG/PNG, up to 4 MB).</div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Model</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Category</label>
                                <input type="text" name="category" list="categories" class="form-control" value="{{ old('category', $product->category) }}" placeholder="Slippers, Apparel, Textiles">
                                <datalist id="categories">
                                    @foreach ($categories->merge(['Slippers', 'Apparel', 'Textiles'])->unique() as $c)
                                        <option value="{{ $c }}">
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" list="brands" class="form-control" value="{{ old('brand', $product->brand) }}" placeholder="e.g. Treklite">
                                <datalist id="brands">
                                    @foreach ($brands as $b)
                                        <option value="{{ $b }}">
                                    @endforeach
                                </datalist>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $product->price) }}" required>
                            <div class="form-text">Default price. A variant can have its own price.</div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3" class="form-control">{{ old('description', $product->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Variants --}}
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Variants (size / color)</span>
                        <button type="button" class="btn btn-outline-success btn-sm" id="add-variant">+ Add variant</button>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted mb-2">
                            Each size/color combination has its own SKU and stock. For items with no size or color, keep a single row and leave both blank.
                            @if ($editing) Stock of existing variants is changed in <strong>Inventory</strong> (Receive / Set count) so every change is logged. @endif
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle" id="variants-table">
                                <thead>
                                    <tr>
                                        <th>Model</th><th>Size</th><th>Color</th>
                                        <th style="width:100px">Price</th>
                                        <th style="width:80px">Low at</th>
                                        <th style="width:90px">Stock</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button class="btn btn-success">{{ $editing ? 'Save changes' : 'Save product' }}</button>
            <a href="{{ route('products.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <script>
        const EXISTING = {{ \Illuminate\Support\Js::from(old('variants', $product->variants->map(fn ($v) => [
            'id' => $v->id, 'sku' => $v->sku, 'size' => $v->size, 'color' => $v->color,
            'price' => $v->price, 'low_stock_threshold' => $v->low_stock_threshold, 'quantity' => $v->quantity,
        ])->values()->all())) }};

        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const tbody = document.querySelector('#variants-table tbody');
        let n = 0;

        function addVariant(v = {}) {
            const i = n++;
            const isNew = !v.id;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    ${v.id ? `<input type="hidden" name="variants[${i}][id]" value="${esc(v.id)}">` : ''}
                    <input type="text" name="variants[${i}][sku]" class="form-control form-control-sm" value="${esc(v.sku)}" required>
                </td>
                <td><input type="text" name="variants[${i}][size]" class="form-control form-control-sm" value="${esc(v.size)}"></td>
                <td><input type="text" name="variants[${i}][color]" class="form-control form-control-sm" value="${esc(v.color)}"></td>
                <td><input type="number" step="0.01" min="0" name="variants[${i}][price]" class="form-control form-control-sm" value="${esc(v.price)}" placeholder="default"></td>
                <td><input type="number" min="0" name="variants[${i}][low_stock_threshold]" class="form-control form-control-sm" value="${esc(v.low_stock_threshold ?? 5)}" required></td>
                <td>${isNew
                    ? `<input type="number" min="0" name="variants[${i}][quantity]" class="form-control form-control-sm" value="${esc(v.quantity ?? 0)}" title="Opening stock">`
                    : `<span class="text-muted">${esc(v.quantity)}</span>`}</td>
                <td>${isNew ? '<button type="button" class="btn btn-sm btn-outline-danger remove-variant">&times;</button>' : ''}</td>`;
            tbody.appendChild(tr);
        }

        document.getElementById('add-variant').addEventListener('click', () => addVariant());
        tbody.addEventListener('click', e => {
            if (e.target.closest('.remove-variant') && tbody.children.length > 1) e.target.closest('tr').remove();
        });

        (EXISTING.length ? EXISTING : [{}]).forEach(addVariant);

        // Live photo preview
        document.getElementById('photo-input').addEventListener('change', e => {
            const f = e.target.files[0];
            if (!f) return;
            const img = document.getElementById('photo-preview');
            img.src = URL.createObjectURL(f);
            img.classList.remove('d-none');
            document.getElementById('photo-placeholder')?.classList.add('d-none');
        });
    </script>
@endsection