<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FormField from '@/Components/Admin/FormField.vue';
import PaymentStatusText from '@/Components/Admin/PaymentStatusText.vue';
import OrderStatusPill from '@/Components/Admin/OrderStatusPill.vue';
import { confirmDanger, toastFromInertiaError, toastSuccess, toastError } from '@/admin/swalToast';
import { useFocusTrap } from '@/admin/useFocusTrap';
import WhatsAppSendPanel from '@/Components/Admin/WhatsAppSendPanel.vue';

const props = defineProps({
    order: { type: Object, required: true },
    whatsapp_templates: { type: Array, default: () => [] },
    whatsapp_send_route: { type: String, default: '' },
    whatsapp_confirmation: { type: Object, default: null },
    order_statuses: { type: Array, required: true },
    payment_statuses: { type: Array, required: true },
    couriers: { type: Array, default: () => [] },
    defaultBookingCourierId: { type: Number, default: null },
});

const form = useForm({
    status: props.order.status,
    payment_status: props.order.payment_status,
    preferred_courier_id: props.order.preferred_courier_id,
    courier_assignment: props.order.courier_assignment,
});

const bookingCourierId = ref(
    props.defaultBookingCourierId
        ?? props.order.preferred_courier_id
        ?? props.couriers[0]?.id
        ?? null,
);
const bookingInFlight = ref(false);
const syncInFlight = ref(false);

const adminDiscountForm = useForm({
    type: props.order.admin_discount?.type ?? 'fixed',
    value: props.order.admin_discount?.value ?? 0,
    reason: props.order.admin_discount?.reason ?? '',
});

const returnModalOpen = ref(false);
const returnDialogRef = ref(null);
useFocusTrap(returnDialogRef, computed(() => returnModalOpen.value), () => { returnModalOpen.value = false; });

const returnForm = useForm({
    reason: '',
    restock: true,
    items: (props.order.items ?? []).map((it) => ({ order_item_id: it.id, qty: 0 })),
});

const expandedPaymentMeta = ref(new Set());

function toggleMeta(id) {
    const next = new Set(expandedPaymentMeta.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expandedPaymentMeta.value = next;
}

const selectedBookingCourier = computed(
    () => props.couriers.find((c) => c.id === bookingCourierId.value) ?? null,
);

const hasShipmentToSync = computed(
    () => (props.order.shipments ?? []).some((s) => !!s.tracking_number),
);

const hasPostExShipment = computed(
    () => (props.order.shipments ?? []).some((s) => s.courier?.adapter === 'postex' && !!s.tracking_number),
);

function submit() {
    form.patch(route('admin.orders.update', props.order.id), {
        preserveScroll: true,
        onSuccess: () => toastSuccess('Order updated'),
        onError: (errors) => toastFromInertiaError(errors, 'Failed to update order'),
    });
}

function money(n) {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(n);
}

async function bookOrder() {
    if (bookingInFlight.value || !bookingCourierId.value) {
        return;
    }

    const courierName = selectedBookingCourier.value?.name ?? 'selected courier';
    const ok = await confirmDanger({
        title: `Book shipment with ${courierName}?`,
        text: `A BookShipmentJob will be queued for order ${props.order.order_number}. You can cancel from the courier dashboard after.`,
        confirmText: `Yes, book with ${courierName}`,
    });
    if (!ok) return;

    bookingInFlight.value = true;
    router.post(
        route('admin.orders.shipment.book', props.order.id),
        { courier_id: bookingCourierId.value },
        {
            preserveScroll: true,
            onSuccess: () => toastSuccess('Shipment booking queued'),
            onError: () => toastError('Failed to book shipment'),
            onFinish: () => {
                bookingInFlight.value = false;
            },
        },
    );
}

function queueSync() {
    if (syncInFlight.value) {
        return;
    }
    syncInFlight.value = true;
    router.post(
        route('admin.orders.shipment.sync-tracking', props.order.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toastSuccess('Tracking sync queued'),
            onError: () => toastError('Failed to sync tracking'),
            onFinish: () => {
                syncInFlight.value = false;
            },
        },
    );
}

async function cancelPostExShipment(shipmentId) {
    if (!shipmentId) return;
    const ok = await confirmDanger({
        title: 'Cancel this shipment?',
        text: 'This will cancel the PostEx shipment and cannot be undone.',
        confirmText: 'Yes, cancel',
    });
    if (!ok) return;
    router.post(
        route('admin.orders.shipment.postex.cancel', [props.order.id, shipmentId]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => toastSuccess('Shipment canceled'),
            onError: () => toastError('Failed to cancel shipment'),
        },
    );
}

function shipLabel(s) {
    const map = {
        pending: 'Pending',
        booked: 'Booked',
        in_transit: 'In transit',
        delivered: 'Delivered',
        failed: 'Failed',
        canceled: 'Canceled',
    };
    return map[s] ?? s;
}

function initials(nameOrEmail) {
    const s = String(nameOrEmail || '').trim();
    if (!s) return 'U';
    const parts = s.split(/\s+/).filter(Boolean);
    const letters = parts.slice(0, 2).map((p) => p[0]?.toUpperCase()).join('');
    return letters || s.slice(0, 2).toUpperCase();
}

const shippingAddress = computed(() => props.order.shipping_address_snapshot || {});

function addressLine(addr) {
    if (!addr) return '—';
    const parts = [addr.line1, addr.area, addr.city].filter((x) => String(x || '').trim() !== '');
    const line = parts.join(', ');
    return line || '—';
}

function postalLine(addr) {
    if (!addr) return '';
    const pc = String(addr.postal_code || '').trim();
    return pc ? pc : '';
}

function itemSubtitle(it) {
    const bits = [];
    if (it.variant_label) bits.push(it.variant_label);
    if (it.size_label) bits.push(`Size ${it.size_label}`);
    if (it.sku) bits.push(it.sku);
    return bits.join(' · ');
}

function computeDiscountAmount() {
    const type = adminDiscountForm.type;
    const value = Number(adminDiscountForm.value || 0);
    if (!value) return 0;
    if (type === 'percent') {
        const base = Number(props.order.subtotal || 0);
        return Math.max(0, (base * value) / 100);
    }
    return Math.max(0, value);
}

async function saveAdminDiscount() {
    const grand = Number(props.order.grand_total || 0);
    const currentAdminDiscount = Number(props.order.admin_discount?.value
        ? (props.order.admin_discount.type === 'percent'
            ? (Number(props.order.subtotal || 0) * Number(props.order.admin_discount.value)) / 100
            : props.order.admin_discount.value)
        : 0);
    const nextAdminDiscount = computeDiscountAmount();
    const projected = Math.max(0, grand + currentAdminDiscount - nextAdminDiscount);

    const ok = await confirmDanger({
        title: 'Apply admin discount?',
        text: `Current total: ${money(grand)} → projected: ${money(projected)}. Server will recompute exact totals on save.`,
        confirmText: 'Yes, save discount',
    });
    if (!ok) return;

    adminDiscountForm.post(route('admin.orders.admin-discount.set', props.order.id), {
        preserveScroll: true,
        onSuccess: () => toastSuccess('Admin discount saved'),
        onError: (errors) => toastFromInertiaError(errors, 'Failed to save admin discount'),
    });
}

function openReturnModal() {
    returnForm.reason = '';
    returnForm.restock = true;
    returnForm.items = (props.order.items ?? []).map((it) => ({ order_item_id: it.id, qty: 0 }));
    returnModalOpen.value = true;
}

async function submitReturn() {
    const picked = (returnForm.items || []).filter((x) => Number(x.qty || 0) > 0);
    if (!picked.length) {
        toastError('Select at least one item qty to return');
        return;
    }

    const totalUnits = picked.reduce((sum, x) => sum + Number(x.qty || 0), 0);
    const ok = await confirmDanger({
        title: `Create return for ${totalUnits} unit(s)?`,
        text: `Reason: "${(returnForm.reason || '').trim() || '(none)'}". Restock: ${returnForm.restock ? 'yes' : 'no'}. This updates inventory and order totals — it cannot be undone from this screen.`,
        confirmText: 'Yes, create return',
    });
    if (!ok) return;

    returnForm.post(
        route('admin.orders.returns.store', props.order.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                returnModalOpen.value = false;
                toastSuccess('Return created');
            },
            onError: (errors) => toastFromInertiaError(errors, 'Failed to create return'),
        },
    );
}

/** POST PDF endpoints open in a new tab (same pattern as Orders index bulk print). */
function submitPdfToNewTab(routeName, payload) {
    const el = document.createElement('form');
    el.method = 'POST';
    el.action = route(routeName);
    el.target = '_blank';

    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = token;
        el.appendChild(csrf);
    }

    Object.entries(payload || {}).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            value.forEach((v) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `${key}[]`;
                input.value = String(v);
                el.appendChild(input);
            });
            return;
        }
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = value === null || value === undefined ? '' : String(value);
        el.appendChild(input);
    });

    document.body.appendChild(el);
    el.submit();
    el.remove();
}

function printShippingLabel() {
    submitPdfToNewTab('admin.orders.bulk.print-labels', {
        order_ids: [props.order.id],
        layout: 'one_per_page',
        paper_size: 'a6',
    });
}

function printPackingSlip() {
    submitPdfToNewTab('admin.orders.bulk.print-packing-slips', { order_ids: [props.order.id] });
}
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />
    <AdminLayout>
        <!-- Theme-like header -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 row-gap-2">
            <div class="d-flex flex-column justify-content-center">
                <div class="mb-1 d-flex align-items-center flex-wrap gap-2">
                    <span class="h5 mb-0">Order {{ order.order_number }}</span>
                    <PaymentStatusText :status="order.payment_status" />
                    <OrderStatusPill :status="order.status" />
                </div>
                <p class="mb-0 text-muted small">{{ order.created_at_human ?? order.created_at }}</p>
            </div>
            <div class="d-flex align-content-center flex-wrap gap-2">
                <button type="button" class="btn btn-primary" @click="printShippingLabel">
                    <i class="icon-base ti tabler-printer icon-18px me-1"></i>
                    Shipping label
                </button>
                <button type="button" class="btn btn-outline-secondary" @click="printPackingSlip">
                    <i class="icon-base ti tabler-file-text icon-18px me-1"></i>
                    Packing slip
                </button>
                <Link class="btn btn-label-secondary" :href="route('admin.orders.index')">
                    <i class="icon-base ti tabler-arrow-left icon-18px me-1"></i>
                    Back
                </Link>
            </div>
        </div>

        <div class="row g-3 align-items-start">
            <div class="col-12 col-xl-8">
                <div class="card mb-3">
                    <div class="card-header py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h6 class="card-title m-0">Products</h6>
                        <span class="text-muted small">{{ order.items?.length ?? 0 }} items · {{ money(order.grand_total) }}</span>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li
                            v-for="it in order.items"
                            :key="it.id"
                            class="list-group-item px-3 py-2"
                        >
                            <div class="d-flex align-items-center gap-2">
                                <div
                                    class="flex-shrink-0 border rounded bg-body-tertiary overflow-hidden d-flex align-items-center justify-content-center"
                                    style="width: 36px; height: 36px;"
                                >
                                    <img
                                        v-if="it.image_url"
                                        :src="it.image_url"
                                        :alt="it.image_alt || it.product_name"
                                        class="w-100 h-100 object-fit-cover"
                                    />
                                    <span v-else class="text-muted" style="font-size: 10px;">—</span>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="fw-semibold small text-truncate mb-0">{{ it.product_name }}</div>
                                    <div v-if="itemSubtitle(it)" class="text-muted text-truncate" style="font-size: 11px; line-height: 1.25;">
                                        {{ itemSubtitle(it) }}
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="fw-semibold small">{{ money(it.line_total) }}</div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        {{ it.quantity }} × {{ money(it.unit_price) }}
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li v-if="(order.items || []).length === 0" class="list-group-item text-muted small py-3">
                            No items.
                        </li>
                    </ul>
                    <div class="card-footer py-2 bg-body-tertiary">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small">
                            <div class="d-flex flex-wrap gap-2 text-muted">
                                <span>Sub <span class="text-body fw-semibold">{{ money(order.subtotal) }}</span></span>
                                <span>Disc <span class="text-body fw-semibold">{{ money(order.discount_total) }}</span></span>
                                <span>Ship <span class="text-body fw-semibold">{{ money(order.shipping_total) }}</span></span>
                                <span>COD <span class="text-body fw-semibold">{{ money(order.cod_fee) }}</span></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">Pay</span>
                                <span class="badge bg-label-secondary">{{ order.payment_gateway ?? '—' }}</span>
                                <span class="fw-semibold">{{ money(order.grand_total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h6 class="card-title m-0">Fulfillment &amp; shipping</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-primary" @click="printShippingLabel">
                                <i class="icon-base ti tabler-printer icon-16px"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-label-secondary" title="Packing slip" @click="printPackingSlip">
                                <i class="icon-base ti tabler-file-text icon-16px"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body pt-2 pb-3">
                        <form @submit.prevent="submit">
                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <FormField id="o_status" label="Order status" :error="form.errors.status">
                                        <template #default="{ invalid, describedBy }">
                                            <select id="o_status" v-model="form.status" class="form-select form-select-sm" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                                <option v-for="o in order_statuses" :key="o.value" :value="o.value">{{ o.label }}</option>
                                            </select>
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField id="o_pay_status" label="Payment status" :error="form.errors.payment_status">
                                        <template #default="{ invalid, describedBy }">
                                            <select id="o_pay_status" v-model="form.payment_status" class="form-select form-select-sm" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                                <option v-for="o in payment_statuses" :key="o.value" :value="o.value">{{ o.label }}</option>
                                            </select>
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField id="o_assign" label="Courier assignment" :error="form.errors.courier_assignment">
                                        <template #default="{ invalid, describedBy }">
                                            <select id="o_assign" v-model="form.courier_assignment" class="form-select form-select-sm" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                                <option value="auto">Automatic (preferred/default)</option>
                                                <option value="manual">Manual (choose below)</option>
                                            </select>
                                        </template>
                                    </FormField>
                                </div>
                                <div class="col-12 col-md-6">
                                    <FormField id="o_pref" label="Preferred courier" :error="form.errors.preferred_courier_id">
                                        <template #default="{ invalid, describedBy }">
                                            <select id="o_pref" v-model="form.preferred_courier_id" class="form-select form-select-sm" :class="{ 'is-invalid': invalid }" :aria-describedby="describedBy">
                                                <option :value="null">None</option>
                                                <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.adapter }})</option>
                                            </select>
                                        </template>
                                    </FormField>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm mt-2" :disabled="form.processing">
                                Save status &amp; courier prefs
                            </button>
                        </form>

                        <hr class="my-3" />

                        <div class="small text-uppercase text-muted fw-semibold mb-2" style="letter-spacing: 0.04em;">Carrier booking</div>
                        <label class="form-label small mb-1">Book with courier</label>
                        <select v-model.number="bookingCourierId" class="form-select form-select-sm">
                            <option v-if="!couriers.length" :value="null" disabled>No active couriers</option>
                            <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }} ({{ c.adapter }})</option>
                        </select>

                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-primary" :disabled="!bookingCourierId || bookingInFlight" @click="bookOrder">
                                {{ bookingInFlight ? 'Queuing…' : `Book shipment — ${selectedBookingCourier?.name ?? 'courier'}` }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                :disabled="!hasShipmentToSync || syncInFlight"
                                :title="hasShipmentToSync ? 'Sync tracking from courier' : 'Book a shipment first'"
                                @click="queueSync"
                            >
                                {{ syncInFlight ? 'Syncing…' : 'Sync tracking' }}
                            </button>
                            <a
                                v-if="hasPostExShipment"
                                class="btn btn-sm btn-outline-secondary"
                                :href="route('admin.orders.postex.load-sheet', props.order.id)"
                                target="_blank"
                                rel="noopener"
                            >
                                PostEx load sheet
                            </a>
                        </div>

                        <div class="mt-3">
                            <div class="small text-uppercase text-muted fw-semibold mb-2" style="letter-spacing: 0.04em;">Shipments</div>
                            <div v-if="order.shipments?.length" class="d-flex flex-column gap-2">
                                <div v-for="s in order.shipments" :key="s.id" class="border rounded p-2 bg-body-tertiary">
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div class="small">
                                            <span class="fw-semibold">#{{ s.id }}</span>
                                            <span class="text-muted ms-1">{{ s.courier?.name ?? '—' }}</span>
                                        </div>
                                        <span class="badge bg-label-secondary">{{ shipLabel(s.status) }}</span>
                                    </div>
                                    <div class="text-muted small mt-1 font-monospace">
                                        {{ s.tracking_number ?? 'No tracking yet' }}
                                    </div>
                                    <div
                                        v-if="s.meta?.booking_error"
                                        class="alert alert-danger py-1 px-2 small mt-2 mb-0"
                                        role="alert"
                                    >
                                        {{ s.meta.booking_error }}
                                    </div>
                                    <div v-if="s.courier?.adapter === 'postex' && s.tracking_number" class="mt-1 d-flex flex-wrap gap-2">
                                        <a
                                            :href="route('admin.orders.shipment.postex.invoice', [order.id, s.id])"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-link btn-sm p-0"
                                        >Invoice PDF</a>
                                        <button
                                            v-if="s.status !== 'canceled'"
                                            type="button"
                                            class="btn btn-link btn-sm text-danger p-0"
                                            @click="cancelPostExShipment(s.id)"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-muted small">No shipment rows yet. Book above to create one.</div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <h6 class="card-title m-0">Payments</h6>
                        <span class="text-muted small">{{ (order.payments || []).length }} attempt(s)</span>
                    </div>
                    <div class="card-body pt-2 pb-2">
                        <div v-if="(order.payments || []).length" class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Gateway</th>
                                        <th>Status</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-nowrap">External id</th>
                                        <th class="text-nowrap">Paid at</th>
                                        <th class="text-nowrap">Created</th>
                                        <th class="text-end" style="width: 90px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="p in order.payments" :key="p.id">
                                        <tr>
                                            <td>
                                                <span class="badge bg-label-secondary">{{ p.gateway ?? '—' }}</span>
                                            </td>
                                            <td><PaymentStatusText :status="p.status" /></td>
                                            <td class="text-end fw-semibold">{{ money(Number(p.amount || 0)) }}</td>
                                            <td class="text-nowrap font-monospace small">{{ p.external_id ?? '—' }}</td>
                                            <td class="text-nowrap text-muted small">{{ p.paid_at ?? '—' }}</td>
                                            <td class="text-nowrap text-muted small">{{ p.created_at ?? '—' }}</td>
                                            <td class="text-end">
                                                <button
                                                    v-if="p.meta && Object.keys(p.meta).length"
                                                    type="button"
                                                    class="btn btn-sm btn-link p-0"
                                                    @click="toggleMeta(p.id)"
                                                >
                                                    {{ expandedPaymentMeta.has(p.id) ? 'Hide' : 'Meta' }}
                                                </button>
                                            </td>
                                        </tr>
                                        <tr v-if="expandedPaymentMeta.has(p.id)">
                                            <td colspan="7" class="bg-body-tertiary">
                                                <pre class="small mb-0 text-body" style="white-space: pre-wrap; word-break: break-word;">{{ JSON.stringify(p.meta, null, 2) }}</pre>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-muted small py-2">
                            No payments recorded yet for this order.
                        </div>
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-header py-2">
                        <h6 class="card-title m-0">Shipping activity &amp; tracking events</h6>
                    </div>
                    <div class="card-body pt-2 pb-2">
                        <ul v-if="order.shipments?.length" class="timeline pb-0 mb-0">
                            <li
                                v-for="s in order.shipments"
                                :key="s.id"
                                class="timeline-item timeline-item-transparent border-primary"
                            >
                                <span class="timeline-point timeline-point-primary"></span>
                                <div class="timeline-event">
                                    <div class="timeline-header d-flex align-items-start justify-content-between gap-2">
                                        <div>
                                            <h6 class="mb-0 small">
                                                Shipment #{{ s.id }} — {{ s.courier?.name ?? '—' }}
                                            </h6>
                                            <div class="text-muted small">
                                                Tracking: <code>{{ s.tracking_number ?? '—' }}</code>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-secondary">{{ shipLabel(s.status) }}</span>
                                    </div>

                                    <ul v-if="s.events?.length" class="list-unstyled mt-2 mb-0 small text-muted">
                                        <li v-for="(e, i) in s.events" :key="i" class="mb-1">
                                            <span class="text-body">{{ e.description ?? e.status }}</span>
                                            <span v-if="e.occurred_at" class="ms-2">{{ e.occurred_at }}</span>
                                        </li>
                                    </ul>
                                </div>
                            </li>
                        </ul>
                        <div v-else class="text-muted small">No shipment rows yet.</div>
                    </div>
                </div>
                    </div>

                    <div class="col-12 col-xl-4">
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <h6 class="card-title m-0">Customer</h6>
                            </div>
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-start align-items-center">
                                    <div class="avatar me-3 bg-label-secondary rounded-circle">
                                        <span class="avatar-initial rounded-circle">
                                            {{ initials(order.customer_name || order.user?.email || order.guest_email) }}
                                        </span>
                                    </div>
                                    <div class="d-flex flex-column min-w-0">
                                        <div class="fw-semibold text-truncate">{{ order.customer_name ?? order.user?.name ?? 'Guest' }}</div>
                                        <div class="text-muted small text-truncate">
                                            {{ order.user?.email ?? order.guest_email ?? '—' }}
                                        </div>
                                        <div v-if="order.customer_phone" class="text-muted small">
                                            <i class="icon-base ti tabler-phone icon-14px me-1"></i>
                                            {{ order.customer_phone }}
                                        </div>
                                    </div>
                                </div>
                                <div v-if="order.customer_notes" class="border rounded p-2 bg-body-tertiary small mt-3">
                                    <div class="fw-semibold mb-1">Order note</div>
                                    <div class="text-muted">{{ order.customer_notes }}</div>
                                </div>
                            </div>
                        </div>

                        <WhatsAppSendPanel
                            v-if="whatsapp_send_route"
                            :send-route="whatsapp_send_route"
                            :templates="whatsapp_templates"
                            recipient-label="Customer phone"
                            :recipient-phone="order.customer_phone"
                            :confirmation="whatsapp_confirmation"
                        />

                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <h6 class="card-title m-0">Shipping address</h6>
                            </div>
                            <div class="card-body py-3">
                                <div class="fw-semibold">{{ shippingAddress.full_name || order.customer_name || '—' }}</div>
                                <div v-if="shippingAddress.phone" class="text-muted small mt-1">
                                    <i class="icon-base ti tabler-phone icon-14px me-1"></i>
                                    {{ shippingAddress.phone }}
                                </div>
                                <div class="text-muted small mt-2">
                                    {{ addressLine(shippingAddress) }}
                                    <span v-if="postalLine(shippingAddress)">, {{ postalLine(shippingAddress) }}</span>
                                </div>
                            </div>
                        </div>

                        <form @submit.prevent="saveAdminDiscount">
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <h6 class="card-title m-0">Admin discount</h6>
                                </div>
                                <div class="card-body pt-3 pb-3">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <FormField id="ad_type" label="Type" :error="adminDiscountForm.errors.type">
                                                <template #default="{ invalid, describedBy }">
                                                    <select
                                                        id="ad_type"
                                                        v-model="adminDiscountForm.type"
                                                        class="form-select form-select-sm"
                                                        :class="{ 'is-invalid': invalid }"
                                                        :aria-describedby="describedBy"
                                                    >
                                                        <option value="fixed">Fixed</option>
                                                        <option value="percent">Percent</option>
                                                    </select>
                                                </template>
                                            </FormField>
                                        </div>
                                        <div class="col-12">
                                            <FormField id="ad_value" label="Value" :error="adminDiscountForm.errors.value">
                                                <template #default="{ invalid, describedBy }">
                                                    <input
                                                        id="ad_value"
                                                        v-model.number="adminDiscountForm.value"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        class="form-control form-control-sm"
                                                        :class="{ 'is-invalid': invalid }"
                                                        :aria-describedby="describedBy"
                                                    />
                                                </template>
                                            </FormField>
                                        </div>
                                        <div class="col-12">
                                            <FormField id="ad_reason" label="Reason" :error="adminDiscountForm.errors.reason">
                                                <template #default="{ invalid, describedBy }">
                                                    <input
                                                        id="ad_reason"
                                                        v-model="adminDiscountForm.reason"
                                                        type="text"
                                                        class="form-control form-control-sm"
                                                        :class="{ 'is-invalid': invalid }"
                                                        :aria-describedby="describedBy"
                                                        placeholder="Optional"
                                                    />
                                                </template>
                                            </FormField>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm mt-2 w-100" :disabled="adminDiscountForm.processing">
                                        Save admin discount
                                    </button>
                                    <div v-if="order.admin_discount" class="text-muted small mt-2">
                                        Current: {{ order.admin_discount.type }} {{ order.admin_discount.value }}{{ order.admin_discount.type === 'percent' ? '%' : '' }}
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="card mb-0">
                            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div>
                                    <h6 class="card-title m-0">Returns</h6>
                                    <p class="text-muted small mb-0 mt-1">Post-sale returns and exchanges for this order.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" @click="openReturnModal">Create return</button>
                            </div>
                            <div class="card-body pt-2 pb-3">
                                <div v-if="order.returns?.length" class="small">
                                    <div v-for="r in order.returns" :key="r.id" class="border rounded p-2 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <div class="fw-semibold">Return #{{ r.id }}</div>
                                            <div class="text-muted">{{ r.created_at }}</div>
                                        </div>
                                        <div class="text-muted mt-1">Reason: {{ r.reason }}</div>
                                        <div class="text-muted">Restock: {{ r.restock ? 'Yes' : 'No' }}</div>
                                    </div>
                                </div>
                                <div v-else class="text-muted small mb-0">No returns yet.</div>
                            </div>
                        </div>
                    </div>
                </div>

        <template v-if="returnModalOpen">
            <div ref="returnDialogRef" class="modal fade show d-block" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="returnModalTitle">
                <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 id="returnModalTitle" class="modal-title">Create return</h5>
                            <button type="button" class="btn-close" aria-label="Close" @click="returnModalOpen = false"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <input v-model="returnForm.reason" type="text" class="form-control" placeholder="e.g. Size issue / damaged / wrong item" />
                                <div v-if="returnForm.errors.reason" class="text-danger small mt-1">{{ returnForm.errors.reason }}</div>
                            </div>
                            <div class="form-check mb-3">
                                <input id="restock" v-model="returnForm.restock" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="restock">Restock items</label>
                            </div>

                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-nowrap">Purchased</th>
                                            <th class="text-nowrap" style="width:120px;">Return qty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(it, idx) in order.items" :key="it.id">
                                            <td>
                                                <div class="fw-semibold">{{ it.product_name }}</div>
                                                <div class="text-muted small">{{ it.sku }} • {{ it.size_label }}</div>
                                            </td>
                                            <td>{{ it.quantity }}</td>
                                            <td>
                                                <input
                                                    v-model.number="returnForm.items[idx].qty"
                                                    type="number"
                                                    min="0"
                                                    :max="it.quantity"
                                                    class="form-control form-control-sm"
                                                />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-if="returnForm.errors.items" class="text-danger small mt-2">{{ returnForm.errors.items }}</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-label-secondary" @click="returnModalOpen = false">Cancel</button>
                            <button type="button" class="btn btn-primary" :disabled="returnForm.processing" @click="submitReturn">Create return</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop fade show"></div>
        </template>
    </AdminLayout>
</template>
