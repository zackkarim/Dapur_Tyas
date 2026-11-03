/**
 * Customer Ordering & Cart Engine - DAPUR TYAS by TECNO GARAGE
 * Manages Cart State, LocalStorage by Table, Quantity Steppers, Bottom Sheet Drawer & Live Filters
 */

const OrderApp = (function () {
    let currentTableId = null;
    let cart = {}; // { menu_id: { id, name, price, qty, photo, note } }

    function getStorageKey() {
        return 'dapur_tyas_cart_table_' + (currentTableId || 'general');
    }

    function loadCart() {
        try {
            const data = localStorage.getItem(getStorageKey());
            cart = data ? JSON.parse(data) : {};
        } catch (e) {
            cart = {};
        }
        updateUI();
    }

    function saveCart() {
        try {
            localStorage.setItem(getStorageKey(), JSON.stringify(cart));
        } catch (e) {}
        updateUI();
    }

    function clearCart() {
        cart = {};
        try {
            localStorage.removeItem(getStorageKey());
        } catch (e) {}
        updateUI();
    }

    function addItem(id, name, price, photo) {
        if (!currentTableId || currentTableId <= 0) {
            if (typeof openTablePicker === 'function') {
                openTablePicker(true);
            }
            if (typeof showToast === 'function') {
                showToast('Silakan pilih nomor meja Anda terlebih dahulu!', 'warning');
            }
            return false;
        }
        id = String(id);
        if (!cart[id]) {
            cart[id] = {
                id: id,
                name: name,
                price: parseFloat(price),
                photo: photo || '',
                qty: 1,
                note: ''
            };
        } else {
            cart[id].qty += 1;
        }
        saveCart();
        return true;
    }

    function changeQty(id, delta) {
        if (!currentTableId || currentTableId <= 0) {
            if (typeof openTablePicker === 'function') {
                openTablePicker(true);
            }
            return false;
        }
        id = String(id);
        if (cart[id]) {
            cart[id].qty += delta;
            if (cart[id].qty <= 0) {
                delete cart[id];
            }
            saveCart();
        }
    }

    function updateNote(id, noteText) {
        id = String(id);
        if (cart[id]) {
            cart[id].note = noteText;
            saveCart();
        }
    }

    function getTotalCount() {
        return Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
    }

    function getTotalAmount() {
        return Object.values(cart).reduce((sum, item) => sum + (item.price * item.qty), 0);
    }

    function formatRupiahJs(num) {
        return 'Rp ' + Number(num).toLocaleString('id-ID');
    }

    function updateUI() {
        const totalCount = getTotalCount();
        const totalAmount = getTotalAmount();

        // Update Header Cart Count & Total Price
        const headerCount = document.getElementById('headerCartCount');
        if (headerCount) {
            headerCount.textContent = totalCount;
            headerCount.style.display = (totalCount > 0) ? 'inline-flex' : 'none';
        }
        const headerTotal = document.getElementById('headerCartTotal');
        if (headerTotal) {
            headerTotal.textContent = formatRupiahJs(totalAmount);
            headerTotal.style.display = (totalCount > 0) ? 'inline-block' : 'none';
        }

        // Update Floating Cart Bar
        const floatingBar = document.getElementById('cartFloatingBar');
        const badgeCount = document.getElementById('cartTotalCount');
        const badgePrice = document.getElementById('cartTotalPrice');

        if (floatingBar && badgeCount && badgePrice) {
            if (totalCount > 0) {
                floatingBar.style.display = 'flex';
                badgeCount.textContent = totalCount;
                badgePrice.textContent = formatRupiahJs(totalAmount);
            } else {
                floatingBar.style.display = 'none';
            }
        }

        // Update Stepper on Menu Item Cards
        document.querySelectorAll('.menu-action-box').forEach(box => {
            const menuId = box.dataset.menuId;
            const itemInCart = cart[menuId];
            const btnAdd = box.querySelector('.btn-add-item');
            const stepper = box.querySelector('.qty-stepper');
            const qtyNum = box.querySelector('.qty-number-display');

            if (btnAdd && stepper && qtyNum) {
                if (itemInCart && itemInCart.qty > 0) {
                    btnAdd.style.display = 'none';
                    stepper.style.display = 'inline-flex';
                    qtyNum.textContent = itemInCart.qty;
                } else {
                    btnAdd.style.display = 'inline-flex';
                    stepper.style.display = 'none';
                }
            }
        });

        // Update Bottom Sheet Drawer Items if present
        renderBottomSheetDrawer();

        // Trigger custom Cart Page Render if on standalone cart.php
        if (typeof renderCartPage === 'function') {
            renderCartPage(cart, totalCount, totalAmount);
        }
    }

    function openCartDrawer() {
        if (!currentTableId || currentTableId <= 0) {
            if (typeof openTablePicker === 'function') {
                openTablePicker(true);
            }
            if (typeof showToast === 'function') {
                showToast('Silakan pilih nomor meja Anda terlebih dahulu!', 'warning');
            }
            return;
        }
        const drawer = document.getElementById('bottomSheetCart');
        const overlay = document.getElementById('drawerOverlay');
        if (drawer && overlay) {
            overlay.classList.add('active');
            drawer.classList.add('active');
            document.body.style.overflow = 'hidden';
            renderBottomSheetDrawer();
        }
    }

    function closeCartDrawer() {
        const drawer = document.getElementById('bottomSheetCart');
        const overlay = document.getElementById('drawerOverlay');
        if (drawer && overlay) {
            overlay.classList.remove('active');
            drawer.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    function renderBottomSheetDrawer() {
        const container = document.getElementById('drawerCartItems');
        const countSpan = document.getElementById('drawerTotalCount');
        const subtotalSpan = document.getElementById('drawerSubtotal');
        const totalSpan = document.getElementById('drawerTotalAmount');
        const btnSubmit = document.getElementById('btnSubmitOrderDrawer');

        if (!container) return;

        const items = Object.values(cart);
        const totalCount = getTotalCount();
        const totalAmount = getTotalAmount();

        if (countSpan) countSpan.textContent = totalCount + ' Item';
        if (subtotalSpan) subtotalSpan.textContent = formatRupiahJs(totalAmount);
        if (totalSpan) totalSpan.textContent = formatRupiahJs(totalAmount);
        if (btnSubmit) btnSubmit.disabled = (items.length === 0);

        if (items.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 36px 16px; color: var(--text-secondary);">
                    <i class="fa-solid fa-basket-shopping" style="font-size: 2.5rem; color: #D1D5DB; margin-bottom: 10px; display: block;"></i>
                    <h4 style="font-size: 1rem; font-weight: 700; color: var(--text-primary); margin-bottom: 4px;">Keranjang Masih Kosong</h4>
                    <p style="font-size: 0.8rem;">Pilih menu makanan atau minuman lezat untuk menambahkan.</p>
                </div>
            `;
            return;
        }

        let html = '';
        items.forEach(it => {
            const sub = it.price * it.qty;
            html += `
                <div style="background: #FAF7F2; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px; margin-bottom: 10px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <div style="font-weight: 700; font-size: 0.92rem; color: var(--text-primary);">${escapeHtml(it.name)}</div>
                            <div style="font-size: 0.78rem; color: var(--primary); font-weight: 700;">${formatRupiahJs(it.price)} &times; ${it.qty} = ${formatRupiahJs(sub)}</div>
                        </div>
                        <button type="button" onclick="OrderApp.changeQty(${it.id}, -${it.qty})" style="background: none; border: none; color: #EF4444; cursor: pointer; padding: 4px;" title="Hapus Item">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>

                    <input type="text" style="width: 100%; margin-top: 8px; padding: 6px 10px; font-size: 0.78rem; border: 1px solid var(--border-subtle); border-radius: 6px; background: #FFFFFF; font-family: var(--font-main);" 
                           placeholder="Catatan porsi ini (misal: pedas sedang, es pisah)..." 
                           value="${escapeHtml(it.note || '')}" 
                           onchange="OrderApp.updateNote(${it.id}, this.value)">

                    <div style="display: flex; justify-content: flex-end; margin-top: 8px;">
                        <div class="qty-stepper">
                            <button type="button" class="btn-qty-mod" onclick="OrderApp.changeQty(${it.id}, -1)"><i class="fa-solid fa-minus"></i></button>
                            <span class="qty-number-display">${it.qty}</span>
                            <button type="button" class="btn-qty-mod" onclick="OrderApp.changeQty(${it.id}, 1)"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Client-side Instant Filtering
    function filterMenuCatalog(catSlug, searchQuery) {
        searchQuery = (searchQuery || '').toLowerCase().trim();
        catSlug = catSlug || 'semua';

        document.querySelectorAll('.menu-card-item').forEach(card => {
            const name = (card.dataset.menuName || '').toLowerCase();
            const desc = (card.dataset.menuDesc || '').toLowerCase();
            const category = card.dataset.categorySlug || '';

            const matchSearch = (!searchQuery || name.includes(searchQuery) || desc.includes(searchQuery));
            const matchCat = (catSlug === 'semua' || category === catSlug);

            card.style.display = (matchSearch && matchCat) ? 'flex' : 'none';
        });

        // Toggle active class on category tabs
        document.querySelectorAll('.cat-tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.slug === catSlug);
        });
    }

    return {
        init: function (tableId) {
            currentTableId = tableId;
            loadCart();
        },
        addItem: addItem,
        changeQty: changeQty,
        updateNote: updateNote,
        clearCart: clearCart,
        getCart: function () { return cart; },
        getTotalCount: getTotalCount,
        getTotalAmount: getTotalAmount,
        formatRupiah: formatRupiahJs,
        openCartDrawer: openCartDrawer,
        closeCartDrawer: closeCartDrawer,
        filterMenuCatalog: filterMenuCatalog
    };
})();
