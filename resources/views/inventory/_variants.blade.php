{{-- Variant table. $variants = collection or paginator; $showProduct = show the product name column. --}}
@php $isOwner = auth()->user()->hasFullAccess(); @endphp
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    @if ($showProduct)
                        <th style="width:56px"></th>
                        <th>Product</th>
                    @endif
                    <th>Size / Color</th>
                    <th class="text-end">In stock</th>
                    <th class="text-center">Status</th>
                    @if ($isOwner)
                        <th class="text-end" style="min-width: 430px;">Receive / Count</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($variants as $v)
                    <tr class="{{ $v->isOutOfStock() || $v->isLowStock() ? 'table-danger' : '' }}">
                        @if ($showProduct)
                            <td>
                                @if ($v->product->image_url)
                                    <img src="{{ $v->product->image_url }}" alt="" width="40" height="40" class="rounded" style="object-fit:cover">
                                @else
                                    <div class="rounded bg-light text-secondary d-flex align-items-center justify-content-center" style="width:40px;height:40px"><i class="bi bi-image"></i></div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('inventory.index', ['product' => $v->product_id]) }}" class="text-decoration-none">{{ $v->product->name }}</a>
                                <div class="small text-muted">{{ $v->product->brand }}</div>
                            </td>
                        @endif
                        <td>{{ $v->label }}</td>
                        <td class="text-end fw-semibold">{{ $v->quantity }}</td>
                        <td class="text-center">
                            @if ($v->isOutOfStock())
                                <span class="badge bg-danger">Out of stock</span>
                            @elseif ($v->isLowStock())
                                <span class="badge bg-warning text-dark">Low stock</span>
                            @else
                                <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                        @if ($isOwner)
                            <td class="text-end">
                                <form method="POST" action="{{ route('inventory.stock', $v) }}" class="d-flex gap-1 justify-content-end">
                                    @csrf
                                    <select name="action" class="form-select form-select-sm" style="width: 100px;" title="Receive = add delivered units. Count = set to the physical count.">
                                        <option value="receive">Receive</option>
                                        <option value="count">Count</option>
                                    </select>
                                    <input type="number" name="quantity" min="0" class="form-control form-control-sm" style="width: 80px;" placeholder="Qty" required>
                                    <input type="text" name="note" maxlength="255" class="form-control form-control-sm" style="width: 150px;" placeholder="Note / reason">
                                    <button class="btn btn-sm btn-success">Submit</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No items match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@if ($isOwner)
    <div class="small text-muted mt-2">
        <strong>Receive</strong> adds the delivered units to the stock.
        <strong>Count</strong> sets the stock to the number physically counted; the system quantity before the count and the difference are saved in the history, and a note is needed when they differ.
    </div>
@endif
