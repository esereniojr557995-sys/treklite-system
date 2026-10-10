@extends('layouts.app')

@section('title', 'New Sale')

@section('content')
    <style>
        .product-card { cursor: pointer; transition: box-shadow .15s, transform .15s; }
        .product-card:hover { box-shadow: 0 .4rem .9rem rgba(0,0,0,.12) !important; transform: translateY(-2px); }
        .product-img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: .375rem .375rem 0 0; }
        .placeholder-img { display: flex; align-items: center; justify-content: center; background: #e9edeb; color: #9aa6a0; font-size: 2.5rem; }
        .cart-panel { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); display: flex; flex-direction: column; }
        .cart-panel .card-body { overflow-y: auto; }
        .cart-lines { max-height: 240px; overflow-y: auto; }
        /* Many products: the catalog scrolls inside its own area, the search stays in view. */
        .catalog-scroll { max-height: calc(100vh - 200px); overflow-y: auto; overflow-x: hidden; padding: .5rem; }

        /* Shopee-style option chips (color / size) */
        .opt-chip { border: 1px solid #ced4da; background: #fff; border-radius: .25rem; padding: .3rem .9rem; font-size: .9rem; cursor: pointer; }
        .opt-chip:hover:not(.disabled) { border-color: #198754; color: #198754; }
        .opt-chip.active { border-color: #198754; color: #198754; background: #e8f5ee; font-weight: 600; }
        .opt-chip.disabled { color: #adb5bd; background: #f8f9fa; text-decoration: line-through; cursor: not-allowed; }
        .modal-thumb { width: 110px; height: 110px; object-fit: cover; border-radius: .375rem; flex: none; }
    </style>

    {{-- Live stock on every card and variant implements Specific Objective 3
         (real-time stock availability lookup). --}}

    <h3 class="mb-3">New Sale</h3>

    <div class="row g-3">
        {{-- ============ LEFT: product catalog ============ --}}
        <div class="col-lg-8">
            <div class="row g-2 mb-3">
                <div class="col-md-8">
                    <input type="search" id="search" class="form-control" placeholder="Search product, brand, size or color...">
                </div>
                <div class="col-md-4">
                    <select id="category" class="form-select">
                        <option value="">All categories</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="catalog-scroll"><div class="row g-3" id="product-grid"></div></div>
        </div>

        {{-- ============ RIGHT: cart + checkout ============ --}}
        <div class="col-lg-4">
            <form method="POST" action="{{ route('sales.store') }}" enctype="multipart/form-data" id="sale-form" class="card shadow-sm cart-panel">
                @csrf
                <div class="card-header fw-bold"><i class="bi bi-cart3"></i> Cart</div>
                <div class="card-body">
                    <div class="cart-lines mb-2" id="cart-lines"></div>
                    <div id="cart-warning" class="alert alert-warning py-1 small d-none"></div>
                    <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                        <span>Total</span><span id="total-display">₱0.00</span>
                    </div>

                    <div class="fw-semibold small text-uppercase text-muted mb-1">Sold to</div>
                    <input type="text" name="customer_name" class="form-control form-control-sm mb-1" placeholder="Customer name" value="{{ old('customer_name') }}">
                    <input type="text" name="customer_address" class="form-control form-control-sm mb-1" placeholder="Address" value="{{ old('customer_address') }}">
                    <input type="text" name="customer_contact" class="form-control form-control-sm mb-3" placeholder="Contact number" value="{{ old('customer_contact') }}">

                    <div class="fw-semibold small text-uppercase text-muted mb-1">Payment</div>
                    <select name="payment_method" id="payment-method" class="form-select form-select-sm mb-2">
                        @foreach (\App\Models\Sale::PAYMENT_LABELS as $key => $label)
                            <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>

                    {{-- Cash --}}
                    <div id="cash-box">
                        <div class="input-group input-group-sm mb-1">
                            <input type="number" step="0.01" min="0" name="amount_tendered" id="tendered" class="form-control" placeholder="Amount received" value="{{ old('amount_tendered') }}">
                            <button type="button" class="btn btn-outline-secondary" id="exact-btn" title="Customer pays the exact total">Exact</button>
                        </div>
                        <div class="small mb-2" id="cash-msg">Change: <strong id="change-display">₱0.00</strong></div>
                    </div>

                    {{-- GCash / Bank transfer: a photo of the payment confirmation is the proof --}}
                    <div id="digital-box" class="d-none">
                        @if ($paymentQr)
                            <div class="text-center mb-2">
                                <img src="{{ $paymentQr }}" alt="Payment QR" style="max-width: 180px;" class="img-fluid border rounded">
                                <div class="small text-muted">Customer scans this QR, then shows the payment confirmation.</div>
                            </div>
                        @endif
                        <label class="form-label small mb-0">Photo of the payment confirmation <span class="text-danger">*</span></label>
                        <input type="file" name="payment_proof" id="payment-proof" accept="image/*" capture="environment" class="form-control form-control-sm mb-1">
                        <div class="small text-muted mb-1">Take a clear photo of the customer's confirmation screen. The amount paid must be visible and must match the total.</div>
                        <div class="small mb-2">Amount to be paid: <strong id="digital-amount">₱0.00</strong></div>
                    </div>

                    <div id="cart-inputs"></div>

                    <button class="btn btn-success w-100" id="submit-btn" disabled>Complete Sale</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Shopee-style variant picker --}}
    <div class="modal fade" id="variant-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-3 mb-3">
                        <div id="modal-image"></div>
                        <div>
                            <div class="fs-4 fw-bold text-success" id="modal-price"></div>
                            <div class="small text-muted" id="modal-stock"></div>
                        </div>
                    </div>

                    <div id="modal-options"></div>

                    <div class="d-flex align-items-center gap-2 mt-2">
                        <span class="small text-muted">Quantity</span>
                        <div class="input-group input-group-sm" style="width: 130px;">
                            <button type="button" class="btn btn-outline-secondary" id="qty-dec">&minus;</button>
                            <input type="number" id="modal-qty" class="form-control text-center" min="1" value="1">
                            <button type="button" class="btn btn-outline-secondary" id="qty-inc">+</button>
                        </div>
                        <span class="small text-muted" id="modal-qty-note"></span>
                    </div>
                </div>
                <div class="modal-footer flex-column align-items-stretch">
                    <div class="small text-danger" id="modal-hint"></div>
                    <button type="button" class="btn btn-success w-100" id="modal-add"><i class="bi bi-cart-plus"></i> Add to cart</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Runs after the page (and Bootstrap, which the layout loads at the bottom) is ready.
        document.addEventListener('DOMContentLoaded', function () {
        try {
        const PRODUCTS = {{ \Illuminate\Support\Js::from($products) }};
        const OLD_ITEMS = {{ \Illuminate\Support\Js::from(old('items', [])) }};

        /* ---------- variant options (color / size) ----------
           Each variant's options come from v.options, or v.color / v.size.
           If a variant has none of those, its label is used as a single "Option" group. */
        const optionsOf = v => {
            const o = {};
            if (v.options && typeof v.options === 'object' && !Array.isArray(v.options)) {
                Object.keys(v.options).forEach(k => { if (v.options[k]) o[k] = String(v.options[k]); });
            } else {
                if (v.color) o['Color'] = String(v.color);
                if (v.size)  o['Size']  = String(v.size);
            }
            return Object.keys(o).length ? o : { 'Option': String(v.label ?? v.sku ?? 'Standard') };
        };

        PRODUCTS.forEach(p => {
            p.variants.forEach(v => { v._opts = optionsOf(v); });
            const names = [];
            p.variants.forEach(v => Object.keys(v._opts).forEach(n => { if (!names.includes(n)) names.push(n); }));
            p.groups = names.map(n => ({
                name: n,
                values: [...new Set(p.variants.map(v => v._opts[n]).filter(x => x !== undefined && x !== ''))]
            }));
        });

        const VARIANTS = {};
        PRODUCTS.forEach(p => p.variants.forEach(v => { VARIANTS[v.id] = { ...v, product: p }; }));
        const variantText = v => Object.values(v._opts).join(' \u00b7 ');

        const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const esc  = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const $    = id => document.getElementById(id);

        let cart = [];                       // [{ id: variantId, qty }]
        let modalProduct = null;
        let sel = {};                        // chosen option per group, e.g. { Color: 'Black', Size: null }
        const modal = new bootstrap.Modal($('variant-modal'));
        const inCartQty = id => (cart.find(l => l.id === id) || { qty: 0 }).qty;

        /* ---------- catalog ---------- */
        function renderCatalog() {
            const q = $('search').value.trim().toLowerCase();
            const cat = $('category').value;

            const list = PRODUCTS.filter(p =>
                (!cat || p.category === cat) &&
                (!q || (p.name + ' ' + (p.brand || '') + ' ' + p.variants.map(v => Object.values(v._opts).join(' ')).join(' ')).toLowerCase().includes(q))
            );

            $('product-grid').innerHTML = list.length ? list.map(card).join('')
                : '<div class="col-12 text-center text-muted py-5">No products found.</div>';
        }

        function card(p) {
            const prices = p.variants.map(v => v.price);
            const min = Math.min(...prices), max = Math.max(...prices);
            const stock = p.variants.reduce((s, v) => s + v.stock, 0);
            const img = p.image
                ? `<img src="${esc(p.image)}" class="product-img" alt="">`
                : `<div class="product-img placeholder-img"><i class="bi bi-image"></i></div>`;
            const summary = p.variants.length > 1
                ? p.groups.map(g => g.values.length + ' ' + g.name.toLowerCase() + (g.values.length > 1 ? 's' : '')).join(' \u00b7 ')
                : '';

            return `<div class="col-6 col-md-4 col-xl-3">
                <div class="card product-card h-100 shadow-sm ${stock <= 0 ? 'opacity-50' : ''}" data-id="${p.id}">
                    ${img}
                    <div class="card-body p-2 d-flex flex-column">
                        <div class="small text-muted text-truncate">${esc(p.brand || p.category || '')}</div>
                        <div class="fw-semibold lh-sm mb-1">${esc(p.name)}</div>
                        <div class="text-success fw-bold">${min === max ? peso(min) : peso(min) + ' – ' + peso(max)}</div>
                        ${summary ? `<div class="small text-muted">${esc(summary)}</div>` : ''}
                        <div class="small mb-2 ${stock <= 0 ? 'text-danger' : 'text-muted'}">${stock <= 0 ? 'Out of stock' : stock + ' in stock'}</div>
                      <button type="button" class="btn btn-success btn-sm w-100 mt-auto" ${stock <= 0 ? 'disabled' : ''}><i class="bi bi-cart-plus"></i> Add to cart</button>
                    </div>
                </div>
            </div>`;
        }

        $('product-grid').addEventListener('click', e => {
            const el = e.target.closest('.product-card');
            if (el) openProduct(parseInt(el.dataset.id, 10));
        });
        $('search').addEventListener('input', renderCatalog);
        $('category').addEventListener('change', renderCatalog);

        /* ---------- Shopee-style variant picker ---------- */
        const matches = (v, s) => Object.keys(s).every(n => s[n] == null || v._opts[n] === s[n]);

        function currentVariant() {
            if (!modalProduct || modalProduct.groups.some(g => sel[g.name] == null)) return null;
            return modalProduct.variants.find(v => matches(v, sel)) || null;
        }

        function openProduct(id) {
            const p = PRODUCTS.find(x => x.id === id);
            if (!p || p.variants.every(v => v.stock <= 0)) return;

            // Products with a single variant go straight into the cart.
            if (p.variants.length === 1) { addToCart(p.variants[0].id, 1); return; }

            modalProduct = p;
            sel = {};
            p.groups.forEach(g => { sel[g.name] = g.values.length === 1 ? g.values[0] : null; });

            $('modal-title').textContent = p.name;
            $('modal-image').innerHTML = p.image
                ? `<img src="${esc(p.image)}" class="modal-thumb" alt="">`
                : `<div class="modal-thumb placeholder-img"><i class="bi bi-image"></i></div>`;
            $('modal-qty').value = 1;
            renderModal();
            modal.show();
        }

        function renderModal() {
            const p = modalProduct;
            const v = currentVariant();

            // option rows: a chip is disabled when nothing in stock matches it together with the other choices
            $('modal-options').innerHTML = p.groups.map(g => {
                const chosen = sel[g.name];
                const chips = g.values.map(val => {
                    const avail = p.variants.some(x => x.stock > 0 && x._opts[g.name] === val && matches(x, { ...sel, [g.name]: null }));
                    return `<button type="button" class="opt-chip ${chosen === val ? 'active' : ''} ${avail ? '' : 'disabled'}"
                                data-group="${esc(g.name)}" data-val="${esc(val)}" ${avail ? '' : 'disabled'}>${esc(val)}</button>`;
                }).join('');
                return `<div class="mb-3">
                    <div class="small text-muted mb-1">${esc(g.name)}${chosen ? ': <span class="text-dark fw-semibold">' + esc(chosen) + '</span>' : ''}</div>
                    <div class="d-flex flex-wrap gap-2">${chips}</div>
                </div>`;
            }).join('');

            // price + stock
            if (v) {
                $('modal-price').textContent = peso(v.price);
                $('modal-stock').textContent = v.stock + ' pieces available';
            } else {
                const pool = p.variants.filter(x => matches(x, sel));
                const prices = pool.map(x => x.price);
                const lo = Math.min(...prices), hi = Math.max(...prices);
                $('modal-price').textContent = lo === hi ? peso(lo) : peso(lo) + ' – ' + peso(hi);
                $('modal-stock').textContent = pool.reduce((s, x) => s + x.stock, 0) + ' in stock';
            }

            // quantity + add button + hint
            const have = v ? inCartQty(v.id) : 0;
            const max = v ? Math.max(0, v.stock - have) : 0;
            $('modal-qty-note').textContent = have > 0 ? have + ' already in your cart' : '';

            const missing = p.groups.filter(g => sel[g.name] == null).map(g => g.name);
            let hint = '';
            if (!v) hint = 'Please select ' + missing.join(' and ');
            else if (max < 1) hint = 'You already have all available stock in your cart.';
            $('modal-hint').textContent = hint;
            $('modal-add').disabled = !v || max < 1;

            clampQty();
        }

        function clampQty() {
            const v = currentVariant();
            const max = v ? Math.max(0, v.stock - inCartQty(v.id)) : 0;
            let q = parseInt($('modal-qty').value, 10) || 1;
            q = Math.max(1, Math.min(q, Math.max(1, max)));
            $('modal-qty').value = q;
            $('modal-qty').max = Math.max(1, max);
            return q;
        }

        $('modal-options').addEventListener('click', e => {
            const chip = e.target.closest('.opt-chip');
            if (!chip || chip.disabled) return;
            const g = chip.dataset.group, val = chip.dataset.val;
            sel[g] = sel[g] === val ? null : val;      // click again to deselect
            renderModal();
        });

        $('qty-dec').addEventListener('click', () => { $('modal-qty').value = (parseInt($('modal-qty').value, 10) || 1) - 1; clampQty(); });
        $('qty-inc').addEventListener('click', () => { $('modal-qty').value = (parseInt($('modal-qty').value, 10) || 1) + 1; clampQty(); });
        $('modal-qty').addEventListener('input', clampQty);

        $('modal-add').addEventListener('click', () => {
            const v = currentVariant();
            if (!v) return;
            addToCart(v.id, clampQty());
            modal.hide();
        });

        /* ---------- cart ---------- */
        function addToCart(variantId, qty) {
            const v = VARIANTS[variantId];
            const line = cart.find(l => l.id === variantId);
            const wanted = (line ? line.qty : 0) + qty;
            const final = Math.min(wanted, v.stock);

            warn(wanted > v.stock ? `Only ${v.stock} of ${v.product.name} (${variantText(v)}) in stock.` : '');
            if (final < 1) return;

            if (line) line.qty = final; else cart.push({ id: variantId, qty: final });
            renderCart();
        }

        function warn(msg) {
            const box = $('cart-warning');
            box.textContent = msg;
            box.classList.toggle('d-none', !msg);
        }

        function cartTotal() {
            return cart.reduce((s, l) => s + VARIANTS[l.id].price * l.qty, 0);
        }

        function renderCart() {
            $('cart-lines').innerHTML = cart.length ? cart.map(l => {
                const v = VARIANTS[l.id];
                return `<div class="d-flex justify-content-between align-items-start border-bottom py-2">
                    <div class="me-2">
                        <div class="fw-semibold small">${esc(v.product.name)}</div>
                        <div class="small text-muted">${esc(variantText(v))} &middot; ${peso(v.price)}</div>
                    </div>
                    <div class="text-end">
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" data-act="dec" data-id="${l.id}">&minus;</button>
                            <span class="btn btn-light disabled">${l.qty}</span>
                            <button type="button" class="btn btn-outline-secondary" data-act="inc" data-id="${l.id}">+</button>
                        </div>
                        <div class="small fw-semibold">${peso(v.price * l.qty)}</div>
                        <button type="button" class="btn btn-outline-danger btn-sm mt-1 py-0 px-2" data-act="rm" data-id="${l.id}" title="Remove from cart"><i class="bi bi-trash"></i></button>
                    </div>
                </div>`;
            }).join('') : '<div class="text-muted small text-center py-3">Cart is empty. Click a product to add it.</div>';

            $('total-display').textContent = peso(cartTotal());
            updateState();
        }

        $('cart-lines').addEventListener('click', e => {
            const btn = e.target.closest('button[data-act]');
            if (!btn) return;
            const id = parseInt(btn.dataset.id, 10);
            const line = cart.find(l => l.id === id);
            if (btn.dataset.act === 'rm') cart = cart.filter(l => l.id !== id);
            if (btn.dataset.act === 'inc') addToCart(id, 1);
            if (btn.dataset.act === 'dec' && line) { line.qty -= 1; if (line.qty < 1) cart = cart.filter(l => l.id !== id); warn(''); }
            renderCart();
        });

        /* ---------- payment ---------- */
        function togglePayment() {
            const cash = $('payment-method').value === 'cash';
            $('cash-box').classList.toggle('d-none', !cash);
            $('digital-box').classList.toggle('d-none', cash);
            $('payment-proof').required = !cash;
            updateState();
        }

        /* The sale can only be completed when the cart has items and the payment is valid:
           cash  -> the amount received is not less than the total (more is fine, change is returned)
           other -> a photo of the payment confirmation is attached */
        function updateState() {
            const total = cartTotal();
            const cash = $('payment-method').value === 'cash';
            let ok = cart.length > 0;
            let msg = '';

            if (cash) {
                const tendered = parseFloat($('tendered').value);
                if (cart.length && (isNaN(tendered) || tendered + 0.001 < total)) {
                    ok = false;
                    msg = isNaN(tendered) ? 'Enter the amount received.' : 'Amount received is less than the total by ' + peso(total - tendered) + '.';
                }
                const change = isNaN(tendered) ? 0 : Math.max(0, tendered - total);
                $('cash-msg').innerHTML = msg ? '<span class="text-danger">' + msg + '</span>' : 'Change: <strong>' + peso(change) + '</strong>';
            } else {
                $('digital-amount').textContent = peso(total);
                if (cart.length && !$('payment-proof').files.length) { ok = false; }
            }
            $('submit-btn').disabled = !ok;
        }

        $('payment-method').addEventListener('change', togglePayment);
        $('tendered').addEventListener('input', updateState);
        $('payment-proof').addEventListener('change', updateState);
        $('exact-btn').addEventListener('click', () => { $('tendered').value = cartTotal().toFixed(2); updateState(); });

        /* ---------- submit ---------- */
        $('sale-form').addEventListener('submit', e => {
            if (!cart.length) { e.preventDefault(); return; }

            if ($('submit-btn').disabled) { e.preventDefault(); return; }

            $('cart-inputs').innerHTML = cart.map((l, i) =>
                `<input type="hidden" name="items[${i}][variant_id]" value="${l.id}">
                 <input type="hidden" name="items[${i}][quantity]" value="${l.qty}">`
            ).join('');
        });

        /* ---------- start ---------- */
        OLD_ITEMS.forEach(i => { if (VARIANTS[i.variant_id]) cart.push({ id: parseInt(i.variant_id, 10), qty: parseInt(i.quantity, 10) || 1 }); });
        renderCatalog();
        togglePayment();
        renderCart();
        } catch (err) {
            console.error(err);
            const g = document.getElementById('product-grid');
            if (g) g.innerHTML = '<div class="col-12"><div class="alert alert-danger">The sale screen could not load: ' + String(err && err.message ? err.message : err) + '</div></div>';
        }
        });
    </script>
@endsection