@extends('layouts.app')

@section('content')
    <h3 class="mb-3">Record a Sale</h3>

    {{--
        Live stock badges next to each product below directly implement
        Specific Objective 3 (real-time stock availability lookup) and
        resolve Problem 3 in the Statement of the Problem: "Delay in
        Customer Service When Checking Stock Availability." Staff no
        longer need to check the paper inventory sheet or walk to the
        shelf — availability for the selected branch updates on screen.
    --}}

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('sales.store') }}" id="sale-form">
                @csrf

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="paymaya">PayMaya</option>
                            <option value="card">Card (tap-to-pay)</option>
                        </select>
                    </div>
                    @if (auth()->user()->hasFullAccess())
                        <div class="col-md-4">
                            <label class="form-label">Branch</label>
                            <select name="branch_id" id="branch-select" class="form-select">
                                @foreach (\App\Models\Branch::all() as $branch)
                                    <option value="{{ $branch->id }}" @selected(auth()->user()->branch_id === $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <table class="table" id="items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="width: 140px;">Available Stock</th>
                            <th style="width: 120px;">Quantity</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>

                <button type="button" class="btn btn-outline-success btn-sm mb-3" id="add-item">+ Add Item</button>

                <div id="stock-warning" class="alert alert-danger d-none py-2"></div>

                <div class="d-flex justify-content-end mb-3">
                    <h5>Total: ₱<span id="total-display">0.00</span></h5>
                </div>

                <button class="btn btn-success" id="submit-btn">Complete Sale</button>
                <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>

    {{-- Product catalog with prices AND per-branch stock, for the live
         availability lookup and subtotal calculation. --}}
    <script>
        const PRODUCTS = @json($products);
        const DEFAULT_BRANCH_ID = @json($defaultBranchId);

        let rowCount = 0;

        function currentBranchId() {
            const branchSelect = document.getElementById('branch-select');
            return branchSelect ? parseInt(branchSelect.value, 10) : DEFAULT_BRANCH_ID;
        }

        function stockFor(product, branchId) {
            const stock = product.stockByBranch[branchId];
            return stock === undefined ? 0 : stock;
        }

        function addRow() {
            const tbody = document.querySelector('#items-table tbody');
            const index = rowCount++;

            const options = PRODUCTS.map(p =>
                `<option value="${p.id}" data-price="${p.price}">${p.name} (₱${p.price.toFixed(2)})</option>`
            ).join('');

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>
                    <select name="items[${index}][product_id]" class="form-select product-select" required>
                        ${options}
                    </select>
                </td>
                <td class="stock-cell"></td>
                <td>
                    <input type="number" name="items[${index}][quantity]" class="form-control qty-input" min="1" value="1" required>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button>
                </td>
            `;
            tbody.appendChild(row);
            updateStockCell(row);
            recalcTotal();
        }

        function updateStockCell(row) {
            const select = row.querySelector('.product-select');
            const product = PRODUCTS.find(p => p.id == select.value);
            const branchId = currentBranchId();
            const stock = product ? stockFor(product, branchId) : 0;
            const qtyInput = row.querySelector('.qty-input');

            const cell = row.querySelector('.stock-cell');
            if (stock <= 0) {
                cell.innerHTML = `<span class="badge bg-danger">Out of stock</span>`;
            } else if (stock <= 5) {
                cell.innerHTML = `<span class="badge bg-warning text-dark">${stock} left</span>`;
            } else {
                cell.innerHTML = `<span class="badge bg-success">${stock} available</span>`;
            }

            qtyInput.dataset.maxStock = stock;
        }

        function updateAllStockCells() {
            document.querySelectorAll('#items-table tbody tr').forEach(updateStockCell);
        }

        function checkOverStock() {
            let overStock = false;
            let overStockNames = [];

            document.querySelectorAll('#items-table tbody tr').forEach(row => {
                const select = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.qty-input');
                const qty = parseInt(qtyInput.value, 10) || 0;
                const max = parseInt(qtyInput.dataset.maxStock || '0', 10);

                if (qty > max) {
                    overStock = true;
                    overStockNames.push(select.selectedOptions[0]?.text || 'item');
                    qtyInput.classList.add('is-invalid');
                } else {
                    qtyInput.classList.remove('is-invalid');
                }
            });

            const warningBox = document.getElementById('stock-warning');
            const submitBtn = document.getElementById('submit-btn');

            if (overStock) {
                warningBox.textContent = `Not enough stock for: ${overStockNames.join(', ')}. Adjust the quantity or check with the Owner/Manager about restocking.`;
                warningBox.classList.remove('d-none');
                submitBtn.disabled = true;
            } else {
                warningBox.classList.add('d-none');
                submitBtn.disabled = false;
            }
        }

        function recalcTotal() {
            let total = 0;
            document.querySelectorAll('#items-table tbody tr').forEach(row => {
                const select = row.querySelector('.product-select');
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(select.selectedOptions[0]?.dataset.price || 0);
                total += price * qty;
            });
            document.getElementById('total-display').textContent = total.toFixed(2);
        }

        document.getElementById('add-item').addEventListener('click', addRow);

        document.getElementById('items-table').addEventListener('input', (e) => {
            if (e.target.classList.contains('qty-input')) {
                checkOverStock();
            }
            recalcTotal();
        });

        document.getElementById('items-table').addEventListener('change', (e) => {
            if (e.target.classList.contains('product-select')) {
                updateStockCell(e.target.closest('tr'));
                checkOverStock();
            }
            recalcTotal();
        });

        document.getElementById('items-table').addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-row')) {
                e.target.closest('tr').remove();
                checkOverStock();
                recalcTotal();
            }
        });

        // Re-check stock for every row whenever the branch changes
        // (Owner/Manager only — this is how they'd cover a branch or
        // compare availability before deciding where to sell from).
        const branchSelect = document.getElementById('branch-select');
        if (branchSelect) {
            branchSelect.addEventListener('change', () => {
                updateAllStockCells();
                checkOverStock();
            });
        }

        // Start with one row
        addRow();
    </script>
@endsection
