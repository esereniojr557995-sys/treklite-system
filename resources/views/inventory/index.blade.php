@extends('layouts.app')

@section('content')
    <h3 class="mb-3">Inventory</h3>

    <div class="card shadow-sm">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Branch</th>
                    <th class="text-end">Quantity</th>
                    <th class="text-center">Status</th>
                    @if (auth()->user()->hasFullAccess())
                        <th class="text-end">Restock</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($inventories as $inv)
                    <tr class="{{ $inv->isLowStock() ? 'table-danger' : '' }}">
                        <td>{{ $inv->product->name }}</td>
                        <td>{{ $inv->branch->name }}</td>
                        <td class="text-end">{{ $inv->quantity }}</td>
                        <td class="text-center">
                            @if ($inv->isLowStock())
                                <span class="badge bg-danger">Low Stock</span>
                            @else
                                <span class="badge bg-success">OK</span>
                            @endif
                        </td>
                        @if (auth()->user()->hasFullAccess())
                            <td class="text-end">
                                <form method="POST" action="{{ route('inventory.restock', $inv) }}" class="d-flex gap-1 justify-content-end">
                                    @csrf
                                    <input type="number" name="quantity" min="1" class="form-control form-control-sm" style="width: 80px;" placeholder="Qty" required>
                                    <button class="btn btn-sm btn-outline-success">Add</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No inventory records.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $inventories->links() }}</div>
@endsection
