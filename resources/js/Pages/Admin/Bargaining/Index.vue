<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    sessions: { type: Object, required: true },
    filters: { type: Object, default: () => ({ state: '', search: '' }) },
    state_options: { type: Array, default: () => [] },
});

const state = reactive({
    state: props.filters?.state ?? '',
    search: props.filters?.search ?? '',
});

watch(
    () => props.filters,
    (f) => {
        state.state = f?.state ?? '';
        state.search = f?.search ?? '';
    },
    { deep: true },
);

function applyFilters() {
    router.get(
        route('admin.bargaining.index'),
        {
            state: state.state || undefined,
            search: state.search || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function money(amount) {
    if (amount == null || amount === '') return '—';
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
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

function outcomeBadgeClass(kind) {
    if (kind === 'success') return 'bg-label-success';
    if (kind === 'danger') return 'bg-label-danger';
    if (kind === 'warning') return 'bg-label-warning';
    return 'bg-label-secondary';
}
</script>

<template>
    <Head title="Admin — Bargaining" />
    <AdminLayout>
        <AdminPageHeader
            title="Bargaining history"
            subtitle="Customer negotiations: accepted offers, walk-aways, incomplete threads, and checkouts that used a bargain price."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Bargaining' }]"
        />

        <div class="card mb-3">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1" for="bf_state">Session state</label>
                        <select id="bf_state" v-model="state.state" class="form-select form-select-sm">
                            <option value="">All outcomes / states</option>
                            <option v-for="o in state_options" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label small mb-1" for="bf_search">Search</label>
                        <input
                            id="bf_search"
                            v-model="state.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Phone, name, SKU, product, email…"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-primary flex-grow-1" @click="applyFilters">Apply</button>
                        <button
                            type="button"
                            class="btn btn-sm btn-label-secondary"
                            @click="state.state = ''; state.search = ''; applyFilters()"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="sessions" empty-title="No bargaining sessions yet">
            <template #head>
                <th>Result</th>
                <th>Product</th>
                <th>Customer</th>
                <th class="text-end">List</th>
                <th class="text-end">Offer</th>
                <th class="text-end">Accepted</th>
                <th>Msgs</th>
                <th>Updated</th>
                <th class="text-end"> </th>
            </template>
            <template #body>
                <tr v-for="s in sessions.data" :key="s.id">
                    <td>
                        <span class="badge" :class="outcomeBadgeClass(s.outcome_kind)">{{ s.outcome }}</span>
                        <div class="text-muted small text-uppercase mt-1" style="font-size: 10px; letter-spacing: 0.04em;">{{ s.state }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold small">{{ s.product_name ?? '—' }}</div>
                        <code class="small text-muted">{{ s.sku ?? '—' }}</code>
                    </td>
                    <td>
                        <div class="small">{{ s.customer_name || '—' }}</div>
                        <div class="text-muted small">{{ s.customer_phone || s.user_email || '—' }}</div>
                    </td>
                    <td class="text-end small">{{ money(s.list_price) }}</td>
                    <td class="text-end small">{{ money(s.current_offer) }}</td>
                    <td class="text-end small">{{ money(s.accepted_price) }}</td>
                    <td class="small">{{ s.messages_count }}</td>
                    <td class="text-muted small text-nowrap">{{ formatWhen(s.updated_at) }}</td>
                    <td class="text-end">
                        <Link class="btn btn-sm btn-outline-primary" :href="route('admin.bargaining.show', s.id)">History</Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
