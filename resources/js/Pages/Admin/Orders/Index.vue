<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import PaymentStatusText from '@/Components/Admin/PaymentStatusText.vue';
import OrderStatusPill from '@/Components/Admin/OrderStatusPill.vue';
import { router } from '@inertiajs/vue3';
import { confirmDanger, toastError, toastFromInertiaError, toastSuccess } from '@/admin/swalToast';
import { useFocusTrap } from '@/admin/useFocusTrap';

const DESTRUCTIVE_PAYMENT_STATUSES = ['paid', 'refunded', 'canceled'];

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    couriers: { type: Array, default: () => [] },
    payment_gateways: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ pending_payment: 0, completed: 0, refunded: 0, failed: 0 }) },
});

const state = reactive({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    payment_status: props.filters?.payment_status ?? '',
    payment_gateway: props.filters?.payment_gateway ?? '',
    delivery_status: props.filters?.delivery_status ?? '',
    courier_id: props.filters?.courier_id ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    preset: props.filters?.preset ?? '',
    per_page: String(props.filters?.per_page ?? 25),
});

const hasAnyFilter = computed(() => Boolean(
    state.search
        || state.status
        || state.payment_status
        || state.payment_gateway
        || state.delivery_status
        || state.courier_id
        || state.date_from
        || state.date_to
        || state.preset,
));

const PRESETS = [
    { key: 'today', label: 'Today' },
    { key: 'today_unbooked', label: 'Today + not booked' },
    { key: 'pending_payment_24h', label: 'Pending payment > 24h' },
    { key: 'booking_failed', label: 'Booking failed' },
];

function togglePreset(key) {
    state.preset = state.preset === key ? '' : key;
}

function currentFilterPayload() {
    return {
        search: state.search || undefined,
        status: state.status || undefined,
        payment_status: state.payment_status || undefined,
        payment_gateway: state.payment_gateway || undefined,
        delivery_status: state.delivery_status || undefined,
        courier_id: state.courier_id || undefined,
        date_from: state.date_from || undefined,
        date_to: state.date_to || undefined,
        preset: state.preset || undefined,
        per_page: state.per_page || undefined,
    };
}

const selectedIds = ref(new Set());
const selectAllOnPage = computed({
    get() {
        const ids = (props.orders?.data ?? []).map((o) => o.id);
        if (!ids.length) return false;
        return ids.every((id) => selectedIds.value.has(id));
    },
    set(v) {
        const ids = (props.orders?.data ?? []).map((o) => o.id);
        const next = new Set(selectedIds.value);
        if (v) {
            ids.forEach((id) => next.add(id));
        } else {
            ids.forEach((id) => next.delete(id));
        }
        selectedIds.value = next;
    },
});

const selectedCount = computed(() => selectedIds.value.size);

const bulkStatusModalOpen = ref(false);
const bulkPaymentModalOpen = ref(false);
const bulkBookingModalOpen = ref(false);
const bulkLabelModalOpen = ref(false);

const bulkStatusDialogRef = ref(null);
const bulkPaymentDialogRef = ref(null);
const bulkBookingDialogRef = ref(null);
const bulkLabelDialogRef = ref(null);

useFocusTrap(bulkStatusDialogRef, computed(() => bulkStatusModalOpen.value), () => { bulkStatusModalOpen.value = false; });
useFocusTrap(bulkPaymentDialogRef, computed(() => bulkPaymentModalOpen.value), () => { bulkPaymentModalOpen.value = false; });
useFocusTrap(bulkBookingDialogRef, computed(() => bulkBookingModalOpen.value), () => { bulkBookingModalOpen.value = false; });
useFocusTrap(bulkLabelDialogRef, computed(() => bulkLabelModalOpen.value), () => { bulkLabelModalOpen.value = false; });

const bulkStatusForm = reactive({
    status: '',
});

const bulkPaymentForm = reactive({
    payment_status: '',
    override: false,
    reason: '',
});

const bulkPaymentIsDestructive = computed(
    () => DESTRUCTIVE_PAYMENT_STATUSES.includes(bulkPaymentForm.payment_status),
);

const bulkPaymentCanSubmit = computed(() => {
    if (!bulkPaymentForm.payment_status) return false;
    if (!bulkPaymentIsDestructive.value) return true;
    return bulkPaymentForm.override && bulkPaymentForm.reason.trim().length > 0;
});

const selectedOrdersPreview = computed(() => {
    const ids = selectedIdArray();
    const lookup = new Map((props.orders?.data ?? []).map((o) => [o.id, o.order_number]));
    return ids.slice(0, 5).map((id) => lookup.get(id) ?? `#${id}`);
});

const bulkBookingForm = reactive({
    mode: 'auto', // auto|manual
    courier_id: null,
});

const bulkLabelForm = reactive({
    layout: 'one_per_page', // one_per_page | three_per_a4
    paper_size: 'a6', // a6|a5 (only for one_per_page)
});

function applyFilters() {
    router.get(
        route('admin.orders.index'),
        currentFilterPayload(),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer = null;
watch(
    () => state.search,
    () => {
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => applyFilters(), 250);
    },
);

watch(
    () => [
        state.status,
        state.payment_status,
        state.payment_gateway,
        state.delivery_status,
        state.courier_id,
        state.date_from,
        state.date_to,
        state.preset,
        state.per_page,
    ],
    () => applyFilters(),
);

function clearFilters() {
    state.search = '';
    state.status = '';
    state.payment_status = '';
    state.payment_gateway = '';
    state.delivery_status = '';
    state.courier_id = '';
    state.date_from = '';
    state.date_to = '';
    state.preset = '';
    applyFilters();
}

function exportCsv() {
    const params = currentFilterPayload();
    Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);
    window.location.href = route('admin.orders.export', params);
}

watch(
    () => props.orders?.data?.map((o) => o.id).join(','),
    () => {
        // Keep selection page-scoped: clear when dataset changes (filters/pagination).
        selectedIds.value = new Set();
    },
);

function selectedIdArray() {
    return Array.from(selectedIds.value.values());
}

function toggleSelected(id, checked) {
    const next = new Set(selectedIds.value);
    if (checked) next.add(id);
    else next.delete(id);
    selectedIds.value = next;
}

function requireSelection() {
    if (!selectedCount.value) {
        alert('Select at least 1 order.');
        return false;
    }
    return true;
}

function bulkSyncTracking() {
    if (!requireSelection()) return;
    router.post(route('admin.orders.bulk.sync-tracking'), { order_ids: selectedIdArray() }, { preserveScroll: true });
}

function bulkUpdateStatus() {
    if (!requireSelection()) return;
    if (!bulkStatusForm.status) {
        toastError('Choose an order status to apply.');
        return;
    }

    const payload = {
        order_ids: selectedIdArray(),
        status: bulkStatusForm.status,
    };
    router.patch(route('admin.orders.bulk.update-status'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            bulkStatusModalOpen.value = false;
            bulkStatusForm.status = '';
            toastSuccess('Order status update queued');
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => toastFromInertiaError(errors, 'Could not update order status'),
    });
}

async function bulkUpdatePaymentStatus() {
    if (!requireSelection()) return;
    if (!bulkPaymentCanSubmit.value) {
        if (bulkPaymentIsDestructive.value) {
            toastError('Tick the override checkbox and provide a reason for destructive payment moves.');
        } else {
            toastError('Choose a payment status to apply.');
        }
        return;
    }

    if (bulkPaymentIsDestructive.value) {
        const previewLine = selectedOrdersPreview.value.join(', ');
        const ok = await confirmDanger({
            title: `Set ${bulkPaymentForm.payment_status} on ${selectedCount.value} order(s)?`,
            text: `This bypasses payment-gateway settlement. Affected: ${previewLine}${selectedCount.value > 5 ? '…' : ''}. Reason: "${bulkPaymentForm.reason.trim()}".`,
            confirmText: `Yes, apply to ${selectedCount.value} order(s)`,
        });
        if (!ok) return;
    }

    const payload = {
        order_ids: selectedIdArray(),
        payment_status: bulkPaymentForm.payment_status,
        override: bulkPaymentIsDestructive.value ? bulkPaymentForm.override : false,
        reason: bulkPaymentForm.reason.trim(),
    };

    router.patch(route('admin.orders.bulk.update-payment-status'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            bulkPaymentModalOpen.value = false;
            bulkPaymentForm.payment_status = '';
            bulkPaymentForm.override = false;
            bulkPaymentForm.reason = '';
            toastSuccess('Payment status update queued');
            router.reload({ preserveScroll: true });
        },
        onError: (errors) => toastFromInertiaError(errors, 'Could not update payment status'),
    });
}

function bulkBookShipments() {
    if (!requireSelection()) return;
    router.post(
        route('admin.orders.bulk.book'),
        {
            order_ids: selectedIdArray(),
            mode: bulkBookingForm.mode,
            courier_id: bulkBookingForm.mode === 'manual' ? bulkBookingForm.courier_id : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                bulkBookingModalOpen.value = false;
                router.reload({ preserveScroll: true });
            },
        },
    );
}

function submitPdfToNewTab(routeName, payload) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route(routeName);
    form.target = '_blank';

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = token;
        form.appendChild(csrf);
    }

    Object.entries(payload || {}).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((v) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${key}[]`;
                input.value = String(v);
                form.appendChild(input);
            });
            return;
        }
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value === null || value === undefined ? '' : String(value);
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function bulkPrintPackingSlips() {
    if (!requireSelection()) return;
    submitPdfToNewTab('admin.orders.bulk.print-packing-slips', { order_ids: selectedIdArray() });
}

function bulkPrintLabels() {
    if (!requireSelection()) return;
    const payload = {
        order_ids: selectedIdArray(),
        layout: bulkLabelForm.layout,
    };
    if (bulkLabelForm.layout === 'one_per_page') {
        payload.paper_size = bulkLabelForm.paper_size;
    }
    submitPdfToNewTab('admin.orders.bulk.print-labels', payload);
}

function money(n) {
    return new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(Number(n || 0));
}

function initials(nameOrEmail) {
    const s = String(nameOrEmail || '').trim();
    if (!s) return 'U';
    const parts = s.split(/\s+/).filter(Boolean);
    const letters = parts.slice(0, 2).map((p) => p[0]?.toUpperCase()).join('');
    return letters || s.slice(0, 2).toUpperCase();
}

function paymentMethodMeta(gateway) {
    const g = String(gateway || '').toLowerCase();
    if (!g) return { icon: 'tabler-help-circle', label: '—' };
    if (g.includes('cod') || g.includes('cash')) return { icon: 'tabler-cash', label: gateway };
    if (g.includes('paypal')) return { icon: 'tabler-brand-paypal', label: gateway };
    if (g.includes('stripe')) return { icon: 'tabler-credit-card', label: gateway };
    if (g.includes('card') || g.includes('master') || g.includes('visa')) return { icon: 'tabler-credit-card', label: gateway };
    if (g.includes('bank')) return { icon: 'tabler-building-bank', label: gateway };
    return { icon: 'tabler-credit-card', label: gateway };
}
</script>

<template>
    <Head title="Admin — Orders" />
    <AdminLayout>
        <!-- KPI widget cards (theme-style) -->
        <div class="card mb-4">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.pending_payment ?? 0 }}</h4>
                                <p class="mb-0">Pending Payment</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded text-heading">
                                    <i class="icon-base ti tabler-calendar-stats icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.completed ?? 0 }}</h4>
                                <p class="mb-0">Completed</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-checks icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.refunded ?? 0 }}</h4>
                                <p class="mb-0">Refunded</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-wallet icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-0">{{ stats.failed ?? 0 }}</h4>
                                <p class="mb-0">Failed</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-alert-octagon icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="orders" empty-title="No orders yet" empty-description="Orders will appear here as customers check out.">
            <template #header>
                <div class="p-4 border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h5 class="card-title mb-0">Filter</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                v-for="preset in PRESETS"
                                :key="preset.key"
                                type="button"
                                class="btn btn-sm"
                                :class="state.preset === preset.key ? 'btn-primary' : 'btn-label-secondary'"
                                @click="togglePreset(preset.key)"
                            >
                                {{ preset.label }}
                            </button>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Order status</label>
                            <select v-model="state.status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Payment status</label>
                            <select v-model="state.payment_status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                                <option value="canceled">Canceled</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Delivery status</label>
                            <select v-model="state.delivery_status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="booked">Booked</option>
                                <option value="in_transit">In transit</option>
                                <option value="delivered">Delivered</option>
                                <option value="failed">Failed</option>
                                <option value="canceled">Canceled</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Courier</label>
                            <select v-model="state.courier_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Payment method</label>
                            <select v-model="state.payment_gateway" class="form-select">
                                <option value="">All</option>
                                <option value="__cod__">COD (cash on delivery)</option>
                                <option value="__prepaid__">Prepaid (card / wallet)</option>
                                <option disabled>──────────</option>
                                <option v-for="g in payment_gateways" :key="g" :value="g">{{ g }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">From</label>
                            <input v-model="state.date_from" type="date" class="form-control" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">To</label>
                            <input v-model="state.date_to" type="date" class="form-control" />
                        </div>
                        <div class="col-12 col-md-3 d-flex align-items-end justify-content-end">
                            <button v-if="hasAnyFilter" type="button" class="btn btn-label-secondary" @click="clearFilters">
                                <i class="icon-base ti tabler-x icon-18px me-1"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div class="input-group" style="max-width: 360px;">
                            <span class="input-group-text">
                                <i class="icon-base ti tabler-search icon-18px"></i>
                            </span>
                            <input v-model="state.search" type="search" class="form-control" placeholder="Search order # or email" />
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                            <div class="btn-group">
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary dropdown-toggle"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                    :disabled="!selectedCount"
                                    :title="selectedCount ? `Bulk actions for ${selectedCount} selected` : 'Select orders first'"
                                >
                                    Bulk actions ({{ selectedCount }})
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" class="dropdown-item" @click="bulkLabelModalOpen = true">
                                            <i class="icon-base ti tabler-printer me-2"></i>
                                            Print shipping labels (PDF)
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item" @click="bulkPrintPackingSlips">
                                            <i class="icon-base ti tabler-file-description me-2"></i>
                                            Print packing slips (PDF)
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider" /></li>
                                    <li>
                                        <button type="button" class="dropdown-item" @click="bulkStatusModalOpen = true">
                                            <i class="icon-base ti tabler-edit me-2"></i>
                                            Bulk order-status update
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item" @click="bulkPaymentModalOpen = true">
                                            <i class="icon-base ti tabler-receipt-2 me-2"></i>
                                            Bulk payment-status update
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item" @click="bulkBookingModalOpen = true">
                                            <i class="icon-base ti tabler-truck-delivery me-2"></i>
                                            Bulk book shipments
                                        </button>
                                    </li>
                                    <li>
                                        <button type="button" class="dropdown-item" @click="bulkSyncTracking">
                                            <i class="icon-base ti tabler-refresh me-2"></i>
                                            Bulk sync tracking
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <select v-model="state.per_page" class="form-select" style="width: 90px;">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <button type="button" class="btn btn-label-secondary" title="Download a CSV of the current filtered view" @click="exportCsv">
                                <i class="icon-base ti tabler-upload icon-xs me-1"></i>
                                Export
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th style="width: 36px;">
                    <input v-model="selectAllOnPage" type="checkbox" class="form-check-input" :disabled="!(orders?.data?.length)" />
                </th>
                <th>Order</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-nowrap">Payment Status</th>
                <th class="text-nowrap">Payment Method</th>
                <th class="text-nowrap">Delivery Status</th>
                <th class="text-nowrap">Order Status</th>
                <th class="text-nowrap text-end">Total</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="o in orders.data" :key="o.id">
                    <td>
                        <input
                            type="checkbox"
                            class="form-check-input"
                            :checked="selectedIds.has(o.id)"
                            @change="(e) => toggleSelected(o.id, e.target.checked)"
                        />
                    </td>
                    <td>
                        <div class="fw-semibold">{{ o.order_number }}</div>
                        <div class="text-muted small">#{{ o.id }}</div>
                    </td>
                    <td class="text-muted small">{{ o.created_at_human ?? '—' }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar avatar-sm bg-label-secondary rounded-circle">
                                <span class="avatar-initial rounded-circle">{{ initials(o.customer_name || o.user?.email || o.guest_email) }}</span>
                            </div>
                            <div class="d-flex flex-column">
                                <div class="fw-semibold">{{ o.customer_name ?? 'Guest' }}</div>
                                <div class="text-muted small">
                                    <span>{{ o.user?.email ?? o.guest_email ?? '—' }}</span>
                                    <span v-if="o.customer_phone" class="ms-2">
                                        <i class="icon-base ti tabler-phone icon-14px me-1"></i>
                                        {{ o.customer_phone }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><PaymentStatusText :status="o.payment_status" /></td>
                    <td class="text-nowrap">
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <i class="icon-base ti" :class="paymentMethodMeta(o.payment_gateway).icon + ' icon-18px'"></i>
                            <span class="text-truncate">{{ paymentMethodMeta(o.payment_gateway).label }}</span>
                        </div>
                    </td>
                    <td><StatusBadge :status="o.delivery_status ?? 'pending'" /></td>
                    <td><OrderStatusPill :status="o.status" /></td>
                    <td class="text-end fw-semibold">{{ money(o.grand_total) }}</td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <i class="icon-base ti tabler-dots-vertical icon-18px"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <Link class="dropdown-item" :href="route('admin.orders.show', o.id)">
                                    <i class="icon-base ti tabler-eye me-2"></i>
                                    View details
                                </Link>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>
        </DataTable>

        <!-- Backdrop must be a sibling of .modal, not inside it — otherwise z-index stacks it above the dialog. -->
        <template v-if="bulkStatusModalOpen">
            <div ref="bulkStatusDialogRef" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="bulkStatusModalTitle">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="bulkStatusModalTitle" class="modal-title">Bulk order-status update</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="bulkStatusModalOpen = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Order status</label>
                                <select v-model="bulkStatusForm.status" class="form-select">
                                    <option value="" disabled>— choose new status —</option>
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="text-muted small">
                                Applies to {{ selectedCount }} selected order(s). Payment status is unaffected — use the dedicated payment action.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" @click="bulkStatusModalOpen = false">Cancel</button>
                            <button type="button" class="btn btn-primary" :disabled="!bulkStatusForm.status" @click="bulkUpdateStatus">Update</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        </template>

        <template v-if="bulkPaymentModalOpen">
            <div ref="bulkPaymentDialogRef" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="bulkPaymentModalTitle">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="bulkPaymentModalTitle" class="modal-title">Bulk payment-status update</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="bulkPaymentModalOpen = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Payment status</label>
                                <select v-model="bulkPaymentForm.payment_status" class="form-select">
                                    <option value="" disabled>— choose new payment status —</option>
                                    <option value="pending">Pending</option>
                                    <option value="paid">Paid</option>
                                    <option value="failed">Failed</option>
                                    <option value="canceled">Canceled</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>

                            <div
                                v-if="bulkPaymentIsDestructive"
                                class="alert alert-warning small mb-3"
                                role="alert"
                            >
                                <div class="fw-semibold mb-1">
                                    <i class="icon-base ti tabler-alert-triangle me-1"></i>
                                    Destructive transition
                                </div>
                                <div>
                                    Setting <code>{{ bulkPaymentForm.payment_status }}</code> on {{ selectedCount }} order(s)
                                    bypasses PSP settlement and is forced into the payment history. Override + reason are required.
                                </div>
                                <div v-if="selectedOrdersPreview.length" class="mt-2 text-body-secondary">
                                    Affected: {{ selectedOrdersPreview.join(', ') }}<span v-if="selectedCount > 5">…</span>
                                </div>
                            </div>

                            <div v-if="bulkPaymentIsDestructive" class="form-check mb-3">
                                <input
                                    id="bulkPaymentOverride"
                                    v-model="bulkPaymentForm.override"
                                    type="checkbox"
                                    class="form-check-input"
                                />
                                <label for="bulkPaymentOverride" class="form-check-label">
                                    I understand this overrides the gateway payment state.
                                </label>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">
                                    Reason
                                    <span v-if="bulkPaymentIsDestructive" class="text-danger">*</span>
                                </label>
                                <textarea
                                    v-model="bulkPaymentForm.reason"
                                    rows="2"
                                    maxlength="200"
                                    class="form-control"
                                    :placeholder="bulkPaymentIsDestructive
                                        ? 'Required — e.g. COD remittance reconciled with courier load sheet #1234'
                                        : 'Optional context for the audit log'"
                                ></textarea>
                                <div class="text-muted small mt-1">{{ bulkPaymentForm.reason.length }}/200</div>
                            </div>
                            <div class="text-muted small">Applies to {{ selectedCount }} selected order(s).</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" @click="bulkPaymentModalOpen = false">Cancel</button>
                            <button
                                type="button"
                                class="btn"
                                :class="bulkPaymentIsDestructive ? 'btn-danger' : 'btn-primary'"
                                :disabled="!bulkPaymentCanSubmit"
                                @click="bulkUpdatePaymentStatus"
                            >
                                {{ bulkPaymentIsDestructive ? 'Override payment state' : 'Update payment status' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        </template>

        <template v-if="bulkBookingModalOpen">
            <div ref="bulkBookingDialogRef" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="bulkBookingModalTitle">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="bulkBookingModalTitle" class="modal-title">Bulk book shipments</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="bulkBookingModalOpen = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Mode</label>
                                <select v-model="bulkBookingForm.mode" class="form-select">
                                    <option value="auto">Auto (preferred/default courier per order)</option>
                                    <option value="manual">Manual (use chosen courier)</option>
                                </select>
                            </div>
                            <div class="mb-2" :class="{ 'opacity-50': bulkBookingForm.mode !== 'manual' }">
                                <label class="form-label">Courier (manual)</label>
                                <select v-model.number="bulkBookingForm.courier_id" class="form-select" :disabled="bulkBookingForm.mode !== 'manual'">
                                    <option :value="null" disabled>Select courier</option>
                                    <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.adapter }})</option>
                                </select>
                            </div>
                            <div class="text-muted small">Queues `BookShipmentJob` for eligible orders.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" @click="bulkBookingModalOpen = false">Cancel</button>
                            <button type="button" class="btn btn-primary" @click="bulkBookShipments">Queue booking</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        </template>

        <template v-if="bulkLabelModalOpen">
            <div ref="bulkLabelDialogRef" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="bulkLabelModalTitle">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="bulkLabelModalTitle" class="modal-title">Print shipping labels</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="bulkLabelModalOpen = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Layout</label>
                                <select v-model="bulkLabelForm.layout" class="form-select">
                                    <option value="one_per_page">One label per page (A6 / A5)</option>
                                    <option value="three_per_a4">3 labels per A4 sheet</option>
                                </select>
                            </div>
                            <div v-if="bulkLabelForm.layout === 'one_per_page'" class="mb-2">
                                <label class="form-label">Paper size</label>
                                <select v-model="bulkLabelForm.paper_size" class="form-select">
                                    <option value="a6">A6</option>
                                    <option value="a5">A5</option>
                                </select>
                            </div>
                            <div v-else class="mb-2 text-muted small">
                                Each A4 page is divided into three equal bands (portrait). Remaining slots on the last page are left blank.
                            </div>
                            <div class="text-muted small">Opens PDF in a new tab.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" @click="bulkLabelModalOpen = false">Cancel</button>
                            <button type="button" class="btn btn-primary" @click="bulkPrintLabels">Print</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        </template>
    </AdminLayout>
</template>
