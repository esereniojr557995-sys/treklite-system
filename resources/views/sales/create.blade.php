@extends('layouts.app')

@section('title', 'New Sale')

@section('content')
    <style>
        .cat-pills { display: flex; gap: .5rem; overflow-x: auto; padding-bottom: .25rem; }
        .cat-pill { white-space: nowrap; border: 1px solid #cfd8d3; background: #fff; border-radius: 999px; padding: .3rem .95rem; font-size: .875rem; cursor: pointer; }
        .cat-pill:hover { border-color: #1e5b31; }
        .cat-pill.active { background: #1e5b31; border-color: #1e5b31; color: #fff; }

        .product-card { border: 1px solid #e3e6e4; cursor: pointer; transition: box-shadow .15s, transform .15s, border-color .15s; overflow: hidden; }
        .product-card:hover { border-color: #1e5b31; box-shadow: 0 .4rem .9rem rgba(0,0,0,.12); transform: translateY(-2px); }
        .product-card.out { opacity: .55; cursor: not-allowed; }
        .product-img { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block; }
        .placeholder-img { display: flex; align-items: center; justify-content: center; background: #e9edeb; color: #9aa6a0; font-size: 2.5rem; }
        .name-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5em; line-height: 1.25; }
        .opt-lines { min-height: 2.6em; font-size: .75rem; line-height: 1.3; }
        .price { color: #ee4d2d; font-weight: 700; }
        .stock-badge { position: absolute; top: .4rem; left: .4rem; font-size: .7rem; }

        .right-col { position: sticky; top: 1rem; max-height: calc(100vh - 2rem); overflow-y: auto; }
        .cart-lines { max-height: 300px; overflow-y: auto; }
        .cart-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: .35rem; background: #e9edeb; flex-shrink: 0; }
        .qty-btn { width: 28px; padding: 0; }

        .variant-chip { min-width: 64px; }
        .variant-chip:disabled { text-decoration: line-through; opacity: .45; }
        .qty-input { width: 60px; text-align: center; }
    </style>

    <h3 class="mb-3">New Sale</h3>

    <div class="row g-3">
        {{-- ============ MIDDLE: product grid ============ --}}
        <div class="col-lg-8">
            <div class="input-group mb-2">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" id="search" class="form-control" placeholder="Search product, brand, SKU, size or color...">
            </div>

            <div class="cat-pills mb-3" id="cat-pills">
                <button type="button" class="cat-pill active" data-cat="">All</button>
                @foreach ($categories as $c)
                    <button type="button" class="cat-pill" data-cat="{{ $c }}">{{ $c }}</button>
                @endforeach
            </div>

            <div class="row g-2" id="product-grid"></div>
        </div>

        {{-- ============ RIGHT: cart + customer/payment + total ============ --}}
        <div class="col-lg-4">
            <form method="POST" action="{{ route('sales.store') }}" enctype="multipart/form-data" id="sale-form" class="right-col">
                @csrf

                {{-- Box 1: cart --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-cart3"></i> Cart</span>
                        <span class="badge bg-success" id="cart-count">0</span>
                    </div>
                    <div class="card-body py-2">
                        <div class="cart-lines" id="cart-lines"></div>
                        <div id="cart-warning" class="alert alert-warning py-1 small d-none mt-2 mb-0"></div>
                    </div>
                </div>

                {{-- Box 2: customer + payment --}}
                <div class="card shadow-sm mb-3">
                    <div class="card-body">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Sold to</div>
                        <input type="text" name="customer_name" class="form-control form-control-sm mb-1" placeholder="Customer name (blank = Walk-in)" value="{{ old('customer_name') }}">
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
                            <input type="number" step="0.01" min="0" name="amount_tendered" id="tendered" class="form-control form-control-sm mb-1" placeholder="Amount received" value="{{ old('amount_tendered') }}">
                            <div class="small text-muted">Change: <strong id="change-display">₱0.00</strong></div>
                        </div>

                        {{-- GCash / PayMaya / Card --}}
                        <div id="digital-box" class="d-none">
                            @if ($paymentQr)
                                <div class="text-center mb-2">
                                    <img src="{{ $paymentQr }}" alt="Payment QR" style="max-width: 160px;" class="img-fluid border rounded">
                                    <div class="small text-muted">Customer scans this QR, then shows the payment confirmation.</div>
                                </div>
                            @endif
                            <input type="text" name="payment_reference" id="payment-ref" class="form-control form-control-sm mb-1" placeholder="Reference number from the receipt" maxlength="100" value="{{ old('payment_reference') }}">
                            <label class="form-label small mb-0">Photo of payment confirmation (optional)</label>
                            <input type="file" name="payment_proof" accept="image/*" capture="environment" class="form-control form-control-sm mb-1">
                            <div class="small text-muted">Verify the payment on the customer's phone before completing the sale.</div>
                        </div>
                    </div>
                </div>

                {{-- Box 3: total + submit --}}
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between fs-5 fw-bold mb-2">
                            <span>Total</span><span id="total-display">₱0.00</span>
                        </div>
                        <div id="cart-inputs"></div>
                        <button class="btn btn-success w-100" id="submit-btn" disabled>
                            <i class="bi bi-check2-circle"></i> Complete Sale
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Variant picker: Size row + Color row --}}
    <div class="modal fade" id="variant-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex gap-3 mb-3">
                        <div id="modal-image" style="width:110px;flex-shrink:0"></div>
                        <div>
                            <div class="price fs-4" id="modal-price"></div>
                            <div class="small text-muted" id="modal-stock"></div>
                            <div class="small text-muted" id="modal-sku"></div>
                        </div>
                    </div>

                    <div id="size-row" class="mb-3">
                        <div class="small text-muted mb-1">Size</div>
                        <div class="d-flex flex-wrap gap-2" id="size-chips"></div>
                    </div>
                    <div id="color-row" class="mb-3">
                        <div class="small text-muted mb-1">Color</div>
                        <div class="d-flex flex-wrap gap-2" id="color-chips"></div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted me-2">Quantity</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm qty-btn" id="modal-minus">&minus;</button>
                        <input type="number" id="modal-qty" class="form-control form-control-sm qty-input" min="1" value="1">
                        <button type="button" class="btn btn-outline-secondary btn-sm qty-btn" id="modal-plus">+</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success w-100" id="modal-add">
                        <i class="bi bi-cart-plus"></i> Add to cart
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const PRODUCTS  = @json($products);
            const OLD_ITEMS = @json(old('items', []));

            /* Work out size + color for every variant.
               Uses v.size / v.color if the controller sends them, otherwise splits "42 / Black". */
            function parseVariant(v) {
                let size = v.size, color = v.color;
                if (size === undefined && color === undefined) {
                    const parts = String(v.label || '').split(' / ').map(s => s.trim()).filter(Boolean);
                    if (parts.length >= 2) { size = parts[0]; color = parts[1]; }
                    else if (parts.length === 1 && parts[0].toLowerCase() !== 'standard') { size = parts[0]; }
                }
                return { size: size || '', color: color || '' };
            }
            PRODUCTS.forEach(p => { p.variants = p.variants.map(v => ({ ...v, ...parseVariant(v) })); });

            const VARIANTS = {};
            PRODUCTS.forEach(p => p.variants.forEach(v => { VARIANTS[v.id] = { ...v, product: p }; }));

            const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const esc  = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const $    = id => document.getElementById(id);
            const uniq = (p, key) => [...new Set(p.variants.map(v => v[key]).filter(Boolean))];
            const inStock = v => v.stock > 0;
            const varText = v => {
                const t = [v.size && `Size: ${v.size}`, v.color && `Color: ${v.color}`].filter(Boolean).join(' · ');
                return t || v.label;
            };

            let cart = [];            // [{ id: variantId, qty }]
            let activeCat = '';
            let modalProduct = null;
            let modalVariant = null;
            let selSize = '', selColor = '';
            const modal = new bootstrap.Modal($('variant-modal'));

            /* ---------- catalog ---------- */
            function renderCatalog() {
                const q = $('search').value.trim().toLowerCase();

                const list = PRODUCTS.filter(p =>
                    (!activeCat || p.category === activeCat) &&
                    (!q || (p.name + ' ' + (p.brand || '') + ' ' +
                        p.variants.map(v => v.sku + ' ' + v.size + ' ' + v.color).join(' ')).toLowerCase().includes(q))
                );

                $('product-grid').innerHTML = list.length ? list.map(card).join('')
                    : '<div class="col-12 text-center text-muted py-5">No products found.</div>';
            }

            function card(p) {
                const prices = p.variants.map(v => v.price);
                const min = Math.min(...prices), max = Math.max(...prices);
                const stock = p.variants.reduce((s, v) => s + v.stock, 0);
                const low = p.variants.some(v => v.stock > 0 && v.stock <= (v.low_stock_threshold ?? 5));
                const sizes = uniq(p, 'size'), colors = uniq(p, 'color');
                const img = p.image
                    ? `<img src="${esc(p.image)}" class="product-img" alt="">`
                    : `<div class="product-img placeholder-img"><i class="bi bi-image"></i></div>`;
                const badge = stock <= 0 ? '<span class="badge bg-secondary stock-badge">Sold out</span>'
                            : (low ? '<span class="badge bg-warning text-dark stock-badge">Low stock</span>' : '');

                return `<div class="col-6 col-md-4 col-xl-3">
                    <div class="card product-card h-100 ${stock <= 0 ? 'out' : ''}" data-id="${p.id}">
                        <div class="position-relative">${img}${badge}</div>
                        <div class="card-body p-2 d-flex flex-column">
                            <div class="name-clamp fw-semibold small mb-1">${esc(p.name)}</div>
                            <div class="small text-muted text-truncate mb-1">${esc(p.brand || p.category || '')}</div>
                            <div class="opt-lines text-muted mb-1">
                                ${sizes.length ? `<div class="text-truncate"><strong>Size:</strong> ${esc(sizes.join(', '))}</div>` : ''}
                                ${colors.length ? `<div class="text-truncate"><strong>Color:</strong> ${esc(colors.join(', '))}</div>` : ''}
                            </div>
                            <div class="price">${min === max ? peso(min) : peso(min) + ' – ' + peso(max)}</div>
                            <div class="small ${stock <= 0 ? 'text-danger' : 'text-muted'} mb-2">${stock <= 0 ? 'Out of stock' : stock + ' in stock'}</div>
                            <button type="button" class="btn btn-outline-success btn-sm w-100 mt-auto" ${stock <= 0 ? 'disabled' : ''}>
                                <i class="bi bi-cart-plus"></i> Add to cart
                            </button>
                        </div>
                    </div>
                </div>`;
            }

            $('product-grid').addEventListener('click', e => {
                const el = e.target.closest('.product-card');
                if (el) openProduct(parseInt(el.dataset.id, 10));
            });
            $('search').addEventListener('input', renderCatalog);
            $('cat-pills').addEventListener('click', e => {
                const pill = e.target.closest('.cat-pill');
                if (!pill) return;
                activeCat = pill.dataset.cat;
                document.querySelectorAll('.cat-pill').forEach(b => b.classList.toggle('active', b === pill));
                renderCatalog();
            });

            /* ---------- variant picker (size + color) ---------- */
            function resolveVariant() {
                return modalProduct.variants.find(v => v.size === selSize && v.color === selColor);
            }

            function openProduct(id) {
                const p = PRODUCTS.find(x => x.id === id);
                if (!p || p.variants.every(v => v.stock <= 0)) return;

                // Products without size/color go straight into the cart.
                if (p.variants.length === 1) { addToCart(p.variants[0].id, 1); return; }

                modalProduct = p;
                $('modal-title').textContent = p.name;
                $('modal-image').innerHTML = p.image
                    ? `<img src="${esc(p.image)}" class="img-fluid rounded" alt="">`
                    : `<div class="product-img placeholder-img rounded"><i class="bi bi-image"></i></div>`;

                const first = p.variants.find(inStock);
                selSize = first.size;
                selColor = first.color;
                renderOptions();
                modal.show();
            }

            function setOption(key, val) {
                if (key === 'size') selSize = val; else selColor = val;

                // If this size/color combination doesn't exist or is sold out,
                // switch the other option to one that works.
                const v = resolveVariant();
                if (!v || !inStock(v)) {
                    const alt = modalProduct.variants.find(x => x[key] === val && inStock(x));
                    if (alt) { selSize = alt.size; selColor = alt.color; }
                }
                renderOptions();
            }

            function renderOptions() {
                const p = modalProduct;
                const sizes = uniq(p, 'size'), colors = uniq(p, 'color');

                $('size-row').classList.toggle('d-none', !sizes.length);
                $('color-row').classList.toggle('d-none', !colors.length);

                const chip = (key, val, selected) => {
                    const ok = p.variants.some(v => v[key] === val && inStock(v));
                    return `<button type="button" class="btn btn-sm variant-chip ${selected === val ? 'btn-success' : 'btn-outline-secondary'}"
                        data-key="${key}" data-val="${esc(val)}" ${ok ? '' : 'disabled'}>${esc(val)}</button>`;
                };
                $('size-chips').innerHTML  = sizes.map(s => chip('size', s, selSize)).join('');
                $('color-chips').innerHTML = colors.map(c => chip('color', c, selColor)).join('');

                modalVariant = resolveVariant();
                const ok = modalVariant && inStock(modalVariant);
                $('modal-add').disabled = !ok;

                if (modalVariant) {
                    $('modal-price').textContent = peso(modalVariant.price);
                    $('modal-stock').textContent = ok ? modalVariant.stock + ' left' : 'Out of stock';
                    $('modal-sku').textContent = 'SKU: ' + modalVariant.sku;
                    $('modal-qty').max = modalVariant.stock;
                } else {
                    $('modal-price').textContent = '';
                    $('modal-stock').textContent = 'This combination is not available';
                    $('modal-sku').textContent = '';
                }
                $('modal-qty').value = 1;
            }

            function clampModalQty(n) {
                const max = modalVariant ? modalVariant.stock : 1;
                $('modal-qty').value = Math.max(1, Math.min(n || 1, max));
            }

            $('variant-modal').addEventListener('click', e => {
                const b = e.target.closest('.variant-chip');
                if (b && !b.disabled) setOption(b.dataset.key, b.dataset.val);
            });
            $('modal-minus').addEventListener('click', () => clampModalQty(parseInt($('modal-qty').value, 10) - 1));
            $('modal-plus').addEventListener('click',  () => clampModalQty(parseInt($('modal-qty').value, 10) + 1));
            $('modal-qty').addEventListener('change',  () => clampModalQty(parseInt($('modal-qty').value, 10)));

            $('modal-add').addEventListener('click', () => {
                if (!modalVariant || !inStock(modalVariant)) return;
                addToCart(modalVariant.id, parseInt($('modal-qty').value, 10) || 1);
                modal.hide();
            });

            /* ---------- cart ---------- */
            function addToCart(variantId, qty) {
                const v = VARIANTS[variantId];
                const line = cart.find(l => l.id === variantId);
                const wanted = (line ? line.qty : 0) + qty;
                const final = Math.min(wanted, v.stock);

                warn(wanted > v.stock ? `Only ${v.stock} of ${v.product.name} (${v.label}) in stock.` : '');
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
                    const thumb = v.product.image
                        ? `<img src="${esc(v.product.image)}" class="cart-thumb" alt="">`
                        : `<div class="cart-thumb d-flex align-items-center justify-content-center text-secondary"><i class="bi bi-image"></i></div>`;
                    return `<div class="d-flex gap-2 border-bottom py-2">
                        ${thumb}
                        <div class="flex-grow-1">
                            <div class="fw-semibold small lh-sm">${esc(v.product.name)}</div>
                            <div class="small text-muted">${esc(varText(v))}</div>
                            <div class="small text-muted">${peso(v.price)} each</div>
                            <div class="d-flex align-items-center justify-content-between mt-1">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary qty-btn" data-act="dec" data-id="${l.id}">&minus;</button>
                                    <span class="btn btn-light disabled px-2">${l.qty}</span>
                                    <button type="button" class="btn btn-outline-secondary qty-btn" data-act="inc" data-id="${l.id}">+</button>
                                </div>
                                <span class="price small">${peso(v.price * l.qty)}</span>
                            </div>
                        </div>
                        <button type="button" class="btn btn-link text-danger p-0 align-self-start" data-act="rm" data-id="${l.id}" title="Remove"><i class="bi bi-trash"></i></button>
                    </div>`;
                }).join('') : '<div class="text-muted small text-center py-4"><i class="bi bi-cart fs-3 d-block mb-1"></i>Your cart is empty.<br>Click a product to add it.</div>';

                $('cart-count').textContent = cart.reduce((s, l) => s + l.qty, 0);
                $('total-display').textContent = peso(cartTotal());
                $('submit-btn').disabled = cart.length === 0;
                updateChange();
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
                $('payment-ref').required = !cash;
            }

            function updateChange() {
                const tendered = parseFloat($('tendered').value);
                const change = isNaN(tendered) ? 0 : Math.max(0, tendered - cartTotal());
                $('change-display').textContent = peso(change);
            }

            $('payment-method').addEventListener('change', togglePayment);
            $('tendered').addEventListener('input', updateChange);

            /* ---------- submit ---------- */
            $('sale-form').addEventListener('submit', e => {
                if (!cart.length) { e.preventDefault(); return; }

                if ($('payment-method').value === 'cash') {
                    const tendered = parseFloat($('tendered').value);
                    if (!isNaN(tendered) && tendered + 0.001 < cartTotal()) {
                        e.preventDefault();
                        warn('The amount received is less than the total.');
                        return;
                    }
                }

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
        });
    </script>
@endsection