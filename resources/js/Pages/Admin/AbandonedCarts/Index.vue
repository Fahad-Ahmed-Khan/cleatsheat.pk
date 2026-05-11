<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';

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

function toggle(id) {
    const next = new Set(expanded.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expanded.value = next;
}

function applyFilters() {
    router.get(
        route('admin.abandoned-carts.index'),
        { search: state.search || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
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
                    <div class="col-12 col-md-8">
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
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary flex-grow-1" @click="applyFilters">Apply</button>
                        <button type="button" class="btn btn-sm btn-label-secondary" @click="state.search = ''; applyFilters()">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="carts" empty-title="No abandoned carts" empty-description="Carts are cleared after a successful order, so only active bags appear here.">
            <template #head>
                <th style="width: 40px"></th>
                <th>Cart</th>
                <th>Account</th>
                <th class="text-end">Lines</th>
                <th class="text-end">Subtotal</th>
                <th>Preview</th>
                <th>Last activity</th>
            </template>
            <template #body>
                <template v-for="c in carts.data" :key="c.id">
                    <tr>
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
                        <td class="align-middle text-end">{{ c.items_count }}</td>
                        <td class="align-middle text-end">{{ money(c.subtotal, c.currency) }}</td>
                        <td class="align-middle small text-muted">{{ lineSummary(c) }}</td>
                        <td class="align-middle text-muted small text-nowrap">{{ formatWhen(c.updated_at) }}</td>
                    </tr>
                    <tr v-if="expanded.has(c.id)">
                        <td colspan="7" class="p-0 bg-body-tertiary">
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
