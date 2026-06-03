const ORDER_STATUS = {
    pending: { label: 'Pending', class: 'bg-amber-100 text-amber-900 ring-amber-200/80' },
    confirmed: { label: 'Confirmed', class: 'bg-sky-100 text-sky-900 ring-sky-200/80' },
    processing: { label: 'Processing', class: 'bg-indigo-100 text-indigo-900 ring-indigo-200/80' },
    shipped: { label: 'Shipped', class: 'bg-violet-100 text-violet-900 ring-violet-200/80' },
    delivered: { label: 'Delivered', class: 'bg-emerald-100 text-emerald-900 ring-emerald-200/80' },
    cancelled: { label: 'Cancelled', class: 'bg-stadium-muted text-stadium-secondary ring-stadium-outline-soft/80' },
};

const PAYMENT_STATUS = {
    pending: { label: 'Payment pending', class: 'bg-amber-50 text-amber-800 ring-amber-200/70' },
    paid: { label: 'Paid', class: 'bg-emerald-50 text-emerald-800 ring-emerald-200/70' },
    failed: { label: 'Payment failed', class: 'bg-red-50 text-red-800 ring-red-200/70' },
    canceled: { label: 'Payment canceled', class: 'bg-stadium-muted text-stadium-secondary ring-stadium-outline-soft/70' },
    refunded: { label: 'Refunded', class: 'bg-slate-100 text-slate-700 ring-slate-200/70' },
};

const badgeBase = 'inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide ring-1';

export function orderStatusBadge(status) {
    const key = String(status ?? '').toLowerCase();
    const meta = ORDER_STATUS[key] ?? { label: status || 'Unknown', class: 'bg-stadium-muted text-stadium-secondary ring-stadium-outline-soft/80' };
    return { ...meta, class: `${badgeBase} ${meta.class}` };
}

export function paymentStatusBadge(status) {
    const key = String(status ?? '').toLowerCase();
    const meta = PAYMENT_STATUS[key] ?? { label: status ? `Payment ${status}` : 'Payment', class: 'bg-stadium-muted text-stadium-secondary ring-stadium-outline-soft/80' };
    return { ...meta, class: `${badgeBase} ${meta.class}` };
}

export function useStoreOrderBadges() {
    return { orderStatusBadge, paymentStatusBadge };
}
