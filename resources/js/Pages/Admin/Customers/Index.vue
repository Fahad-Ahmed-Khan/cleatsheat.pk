<script setup>
import { computed, reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    customers: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, with_orders: 0, guest_orders: 0 }) },
    vip_threshold: { type: Number, default: 50000 },
});

const state = reactive({
    search: props.filters.search ?? '',
    segment: props.filters.segment ?? '',
    per_page: String(props.filters.per_page ?? 25),
});

const hasFilter = computed(() => Boolean(state.search || state.segment));

function applyFilters() {
    router.get(
        route('admin.customers.index'),
        {
            search: state.search || undefined,
            segment: state.segment || undefined,
            per_page: state.per_page || undefined,
        },
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
    () => [state.segment, state.per_page],
    () => applyFilters(),
);

function setSegment(seg) {
    state.segment = state.segment === seg ? '' : seg;
}

function clearFilters() {
    state.search = '';
    state.segment = '';
    applyFilters();
}

function money(n) {
    return new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(Number(n || 0));
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString();
    } catch {
        return '—';
    }
}
</script>

<template>
    <Head title="Admin — Customers" />
    <AdminLayout>
        <div class="card mb-4">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-4">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.total ?? 0 }}</h4>
                                <p class="mb-0">Customer accounts</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-users icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6" />
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.with_orders ?? 0 }}</h4>
                                <p class="mb-0">With ≥ 1 order</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-success rounded">
                                    <i class="icon-base ti tabler-shopping-bag icon-26px"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-0">{{ stats.guest_orders ?? 0 }}</h4>
                                <p class="mb-0">Guest orders (no account)</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-user-question icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="customers" empty-title="No customers match" empty-description="Try a different segment or search term.">
            <template #header>
                <div class="p-4 border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h5 class="card-title mb-0">Filter</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="state.segment === 'has_orders' ? 'btn-primary' : 'btn-label-secondary'"
                                @click="setSegment('has_orders')"
                            >
                                Has orders
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="state.segment === 'no_orders' ? 'btn-primary' : 'btn-label-secondary'"
                                @click="setSegment('no_orders')"
                            >
                                No orders yet
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="state.segment === 'vip' ? 'btn-primary' : 'btn-label-secondary'"
                                :title="`Lifetime spend ≥ ${money(vip_threshold)}`"
                                @click="setSegment('vip')"
                            >
                                VIP
                            </button>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Search</label>
                            <input v-model="state.search" type="search" class="form-control" placeholder="Name, email or phone" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Per page</label>
                            <select v-model="state.per_page" class="form-select">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 d-flex align-items-end justify-content-end">
                            <button v-if="hasFilter" type="button" class="btn btn-label-secondary" @click="clearFilters">
                                <i class="icon-base ti tabler-x icon-18px me-1"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th>Customer</th>
                <th>Phone</th>
                <th class="text-end">Orders</th>
                <th class="text-end">Lifetime spend</th>
                <th class="text-nowrap">Last order</th>
                <th class="text-nowrap">Joined</th>
                <th class="text-end">Actions</th>
            </template>

            <template #body>
                <tr v-for="c in customers.data" :key="c.id">
                    <td>
                        <div class="fw-semibold">{{ c.name ?? '—' }}</div>
                        <div class="text-muted small">{{ c.email }}</div>
                    </td>
                    <td class="small text-muted">{{ c.phone ?? '—' }}</td>
                    <td class="text-end fw-semibold">{{ c.orders_count }}</td>
                    <td class="text-end fw-semibold">{{ money(c.lifetime_spend) }}</td>
                    <td class="text-nowrap text-muted small">{{ formatDate(c.last_order_at) }}</td>
                    <td class="text-nowrap text-muted small">{{ c.created_at_human ?? '—' }}</td>
                    <td class="text-end">
                        <Link :href="route('admin.orders.index', { search: c.email })" class="btn btn-sm btn-outline-secondary">
                            View orders
                        </Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
