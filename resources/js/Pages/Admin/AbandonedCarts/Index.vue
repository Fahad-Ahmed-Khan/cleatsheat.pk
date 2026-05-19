<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import { confirmDanger, toastError, toastSuccess } from '@/admin/swalToast';

const props = defineProps({
    carts: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '' }) },
});

const state = reactive({
    search: props.filters?.search ?? '',
});

watch(
    () => props.filters,
    (f) => {
        state.search = f?.search ?? '';
    },
    { deep: true },
);

const expanded = ref(new Set());
const selectedIds = ref(new Set());
const sendInFlight = ref(new Set());
const bulkSendInFlight = ref(false);

function toggle(id) {
    const next = new Set(expanded.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expanded.value = next;
}

function toggleSelect(id) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}

const reachableCarts = computed(() => (props.carts?.data ?? []).filter((c) => c.is_reachable));
const allSelected = computed(() => reachableCarts.value.length > 0 && reachableCarts.value.every((c) => selectedIds.value.has(c.id)));

function toggleSelectAll() {
    const next = new Set(selectedIds.value);
    if (allSelected.value) {
        reachableCarts.value.forEach((c) => next.delete(c.id));
    } else {
        reachableCarts.value.forEach((c) => next.add(c.id));
    }
    selectedIds.value = next;
}

const selectedCount = computed(() => selectedIds.value.size);

function applyFilters() {
    router.get(
        route('admin.abandoned-carts.index'),
        { search: state.search || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

async function sendOne(cart) {
    if (!cart.is_reachable) {
        toastError('No phone number on file for this account.');
        return;
    }
    const ok = await confirmDanger({
        title: `Send WhatsApp reminder to ${cart.account_label}?`,
        text: `One message will be queued via your configured WhatsApp gateway. Recipient: ${cart.phone}.`,
        confirmText: 'Yes, send reminder',
    });
    if (!ok) return;

    const next = new Set(sendInFlight.value);
    next.add(cart.id);
    sendInFlight.value = next;

    router.post(route('admin.abandoned-carts.whatsapp.send', cart.id), {}, {
        preserveScroll: true,
        onSuccess: () => toastSuccess('WhatsApp reminder queued'),
        onError: () => toastError('WhatsApp reminder failed'),
        onFinish: () => {
            const after = new Set(sendInFlight.value);
            after.delete(cart.id);
            sendInFlight.value = after;
        },
    });
}

async function bulkSend() {
    if (selectedCount.value === 0) {
        toastError('Pick at least one reachable cart.');
        return;
    }
    const ok = await confirmDanger({
        title: `Send WhatsApp to ${selectedCount.value} cart(s)?`,
        text: 'A message is dispatched per row. Skipped rows are reported in the post-action summary.',
        confirmText: 'Yes, send all',
    });
    if (!ok) return;

    bulkSendInFlight.value = true;
    router.post(
        route('admin.abandoned-carts.whatsapp.bulk'),
        { cart_ids: [...selectedIds.value] },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = new Set();
            },
            onError: () => toastError('Bulk send failed'),
            onFinish: () => {
                bulkSendInFlight.value = false;
            },
        },
    );
}

function money(amount, currency = 'PKR') {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(Number(amount));
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-PK', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function lineSummary(cart) {
    const lines = cart.lines || [];
    if (!lines.length) return '—';
    const first = lines[0];
    const extra = lines.length - 1;
    const bit = `${first.product_name ?? 'Item'} × ${first.quantity}`;
    return extra > 0 ? `${bit} +${extra} more` : bit;
}
</script>

<template>
    <Head title="Admin — Abandoned carts" />
    <AdminLayout>
        <AdminPageHeader
            title="Abandoned carts"
            subtitle="Bags that still have items but have not completed checkout — useful for follow-up."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Abandoned carts' }]"
        />

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label small mb-1" for="ac_search">Search</label>
                        <input
                            id="ac_search"
                            v-model="state.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Guest token, customer email or name…"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary flex-grow-1" @click="applyFilters">Apply</button>
                        <button type="button" class="btn btn-sm btn-label-secondary" @click="state.search = ''; applyFilters()">Reset</button>
                    </div>
                    <div class="col-12 col-md-3 d-flex justify-content-md-end">
                        <button
                            type="button"
                            class="btn btn-sm btn-success"
                            :disabled="selectedCount === 0 || bulkSendInFlight"
                            @click="bulkSend"
                        >
                            <i class="icon-base ti tabler-brand-whatsapp me-1"></i>
                            Send WhatsApp to {{ selectedCount }} selected
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="carts" empty-title="No abandoned carts" empty-description="Carts are cleared after a successful order, so only active bags appear here.">
            <template #head>
                <th style="width: 40px">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        :checked="allSelected"
                        :disabled="reachableCarts.length === 0"
                        title="Select all reachable on this page"
                        @change="toggleSelectAll"
                    />
                </th>
                <th style="width: 40px"></th>
                <th>Cart</th>
                <th>Account</th>
                <th>Phone</th>
                <th class="text-end">Lines</th>
                <th class="text-end">Subtotal</th>
                <th>Preview</th>
                <th>Last activity</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <template v-for="c in carts.data" :key="c.id">
                    <tr>
                        <td class="align-middle">
                            <input
                                type="checkbox"
                                class="form-check-input"
                                :checked="selectedIds.has(c.id)"
                                :disabled="!c.is_reachable"
                                @change="toggleSelect(c.id)"
                            />
                        </td>
                        <td class="align-middle">
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-text-secondary"
                                :aria-expanded="expanded.has(c.id)"
                                :title="expanded.has(c.id) ? 'Hide lines' : 'Show lines'"
                                @click="toggle(c.id)"
                            >
                                <i :class="expanded.has(c.id) ? 'ti tabler-chevron-down' : 'ti tabler-chevron-right'" class="icon-base icon-18px"></i>
                            </button>
                        </td>
                        <td class="align-middle font-monospace small">#{{ c.id }}</td>
                        <td class="align-middle">
                            <div class="small fw-semibold">{{ c.account_label }}</div>
                            <div v-if="c.guest_token_short" class="text-muted small">Token {{ c.guest_token_short }}</div>
                        </td>
                        <td class="align-middle small">
                            <span v-if="c.is_reachable" class="font-monospace">{{ c.phone }}</span>
                            <span v-else class="text-muted">—</span>
                        </td>
                        <td class="align-middle text-end">{{ c.items_count }}</td>
                        <td class="align-middle text-end">{{ money(c.subtotal, c.currency) }}</td>
                        <td class="align-middle small text-muted">{{ lineSummary(c) }}</td>
                        <td class="align-middle text-muted small text-nowrap">{{ formatWhen(c.updated_at) }}</td>
                        <td class="align-middle text-end">
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="c.is_reachable ? 'btn-outline-success' : 'btn-outline-secondary'"
                                :disabled="!c.is_reachable || sendInFlight.has(c.id)"
                                :title="c.is_reachable ? 'Send WhatsApp reminder' : 'No phone number on the customer account'"
                                @click="sendOne(c)"
                            >
                                <i class="icon-base ti tabler-brand-whatsapp"></i>
                                <span class="d-none d-md-inline ms-1">{{ sendInFlight.has(c.id) ? 'Sending…' : 'WhatsApp' }}</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="expanded.has(c.id)">
                        <td colspan="10" class="p-0 bg-body-tertiary">
                            <div class="p-3 border-top">
                                <div class="small fw-semibold mb-2">Line items</div>
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 bg-body">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>SKU</th>
                                                <th>Size</th>
                                                <th class="text-end">Qty</th>
                                                <th class="text-end">Unit</th>
                                                <th class="text-end">Line</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="(ln, i) in c.lines" :key="i">
                                                <td>
                                                    <div class="small fw-medium">{{ ln.product_name ?? '—' }}</div>
                                                    <div v-if="ln.variant_label" class="text-muted" style="font-size: 11px;">{{ ln.variant_label }}</div>
                                                </td>
                                                <td><code class="small">{{ ln.sku ?? '—' }}</code></td>
                                                <td>{{ ln.size_label }}</td>
                                                <td class="text-end">{{ ln.quantity }}</td>
                                                <td class="text-end">{{ money(ln.unit_price, c.currency) }}</td>
                                                <td class="text-end">{{ money(ln.line_total, c.currency) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </template>
        </DataTable>
    </AdminLayout>
</template>
