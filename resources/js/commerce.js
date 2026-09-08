function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function formatMoney(currency, amount) {
    const value = Number(amount || 0);
    const prefix = currency === 'INR' ? '₹' : `${currency} `;

    return `${prefix} ${value.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function itemLineTotal(item) {
    const price = Number(item.item_price ?? item.price ?? 0);
    const qty = Number(item.quantity ?? 1);

    return price * qty;
}

export function initCommerceOrderModal() {
    const modal = document.getElementById('modal-order-details');
    if (!modal) return;

    const baseUrl = modal.dataset.orderDetailBase || '/commerce/orders';
    const nameEl = modal.querySelector('[data-order-customer-name]');
    const phoneEl = modal.querySelector('[data-order-customer-phone]');
    const dateEl = modal.querySelector('[data-order-date]');
    const totalEl = modal.querySelector('[data-order-total]');
    const paymentStatusEl = modal.querySelector('[data-order-payment-status]');
    const paymentLinkEl = modal.querySelector('[data-order-payment-link]');
    const itemsEl = modal.querySelector('[data-order-items]');
    const statusSelect = modal.querySelector('[data-order-status-select]');
    const errorEl = modal.querySelector('[data-order-error]');
    const subtitleEl = modal.querySelector('[data-order-subtitle]');

    let currentUuid = null;
    let loading = false;

    const showError = (message) => {
        if (!errorEl) return;
        if (!message) {
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
            return;
        }
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    };

    const renderItems = (items, currency) => {
        if (!itemsEl) return;

        if (!Array.isArray(items) || items.length === 0) {
            itemsEl.innerHTML = '<p class="text-sm text-text-subtle">No items on this order.</p>';
            return;
        }

        itemsEl.innerHTML = items.map((item) => {
            const name = item.name || item.product_retailer_id || 'Product';
            const qty = item.quantity ?? 1;
            const image = item.image_url
                ? `<img src="${item.image_url}" alt="" class="size-16 shrink-0 rounded object-cover" width="64" height="64">`
                : '<div class="size-16 shrink-0 rounded bg-border"></div>';

            return `
              <div class="flex w-full items-center gap-3">
                ${image}
                <div class="min-w-0 flex-1">
                  <p class="text-base font-semibold leading-[1.4] text-text-primary">${name}</p>
                  <p class="text-sm font-normal leading-[1.4] text-text-subtle">Quantity: ${qty}</p>
                </div>
                <p class="shrink-0 text-base font-semibold leading-normal text-green-700">${formatMoney(currency, itemLineTotal(item))}</p>
              </div>
            `;
        }).join('');
    };

    const fillStatusSelect = (statuses, current) => {
        if (!statusSelect) return;
        statusSelect.innerHTML = (statuses || []).map((status) => (
            `<option value="${status.value}" ${status.value === current ? 'selected' : ''}>${status.label}</option>`
        )).join('');
    };

    const loadOrder = async (uuid) => {
        if (!uuid || loading) return;
        loading = true;
        currentUuid = uuid;
        showError('');
        if (subtitleEl) subtitleEl.textContent = 'Loading order…';
        if (itemsEl) itemsEl.innerHTML = '<p class="text-sm text-text-subtle">Loading items…</p>';

        try {
            const response = await fetch(`${baseUrl}/${uuid}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to load order details.');
            }

            const order = await response.json();
            if (currentUuid !== uuid) return;

            if (nameEl) nameEl.textContent = order.customer_name || '—';
            if (phoneEl) phoneEl.textContent = order.customer_phone || '—';
            if (dateEl) dateEl.textContent = order.created_at || '—';
            if (totalEl) totalEl.textContent = formatMoney(order.currency || 'INR', order.total_price);
            if (paymentStatusEl) paymentStatusEl.textContent = order.payment_status_label || order.payment_status || '—';
            if (subtitleEl) subtitleEl.textContent = 'Complete order details';

            if (paymentLinkEl) {
                if (order.payment_link) {
                    paymentLinkEl.href = order.payment_link;
                    paymentLinkEl.classList.remove('hidden');
                } else {
                    paymentLinkEl.classList.add('hidden');
                }
            }

            fillStatusSelect(order.statuses || [], order.order_status);
            renderItems(order.product_items || [], order.currency || 'INR');
        } catch (error) {
            showError(error.message || 'Unable to load order details.');
            if (subtitleEl) subtitleEl.textContent = 'Could not load order';
        } finally {
            loading = false;
        }
    };

    document.querySelectorAll('[data-open-modal="order-details"][data-order-uuid]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const uuid = trigger.dataset.orderUuid;
            if (uuid) {
                loadOrder(uuid);
            }
        });
    });

    statusSelect?.addEventListener('change', async () => {
        if (!currentUuid || !statusSelect.value) return;
        showError('');

        try {
            const response = await fetch(`${baseUrl}/${currentUuid}/status`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ order_status: statusSelect.value }),
            });

            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || 'Unable to update order status.');
            }

            // Refresh list badges without full reload when possible
            const row = document.querySelector(`[data-order-uuid="${currentUuid}"]`);
            if (row) {
                row.dataset.orderStatus = statusSelect.value;
            }
        } catch (error) {
            showError(error.message || 'Unable to update order status.');
            loadOrder(currentUuid);
        }
    });
}
