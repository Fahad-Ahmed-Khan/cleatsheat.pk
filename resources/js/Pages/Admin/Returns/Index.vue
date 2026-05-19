<script setup>
import { computed, reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    returns: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, last_30d: 0, restocked: 0, not_restocked: 0 }) },
});

const state = reactive({
    search: props.filters.search ?? '',
    restock: props.filters.restock ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    per_page: String(props.filters.per_page ?? 25),
});

const hasAnyFilter = computed(() => Boolean(
    state.search || state.restock !== '' || state.date_from || state.date_to,
));

function applyFilters() {
    router.get(
        route('admin.returns.index'),
        {
            search: state.search || undefined,
            restock: state.restock === '' ? undefined : state.restock,
            date_from: state.date_from || undefined,
            date_to: state.date_to || undefined,
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
    () => [state.restock, state.date_from, state.date_to, state.per_page],
    () => applyFilters(),
);

function clearFilters() {
    state.search = '';
    state.restock = '';
    state.date_from = '';
    state.date_to = '';
    applyFilters();
}
</script>

<template>
    <Head title="Admin — Returns" />
    <AdminLayout>
        <div class="card mb-4">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.total ?? 0 }}</h4>
                                <p class="mb-0">All returns</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-package icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.last_30d ?? 0 }}</h4>
                                <p class="mb-0">Last 30 days</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-calendar-stats icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.restocked ?? 0 }}</h4>
                                <p class="mb-0">Restocked</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-success rounded">
                                    <i class="icon-base ti tabler-checks icon-26px"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-0">{{ stats.not_restocked ?? 0 }}</h4>
                                <p class="mb-0">Not restocked</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-warning rounded">
                                    <i class="icon-base ti tabler-x icon-26px"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="returns" empty-title="No returns yet" empty-description="Returns created from order detail pages will appear here.">
            <template #header>
                <div class="p-4 border-bottom">
                    <h5 class="card-title mb-3">Filter</h5>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label small text-muted">Search</label>
                            <input v-model="state.search" type="search" class="form-control" placeholder="Order # or reason" />
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label small text-muted">Restock</label>
                            <select v-model="state.restock" class="form-select">
                                <option value="">All</option>
                                <option value="1">Restocked</option>
                                <option value="0">Not restocked</option>
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
                        <div class="col-12 d-flex align-items-end justify-content-end gap-2">
                            <select v-model="state.per_page" class="form-select" style="width: 90px;">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <button v-if="hasAnyFilter" type="button" class="btn btn-label-secondary" @click="clearFilters">
                                <i class="icon-base ti tabler-x icon-18px me-1"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th>Return</th>
                <th>Order</th>
                <th>Customer</th>
                <th>Reason</th>
                <th class="text-end">Units</th>
                <th>Restock</th>
                <th class="text-nowrap">Created</th>
                <th class="text-end">Actions</th>
            </template>

            <template #body>
                <tr v-for="r in returns.data" :key="r.id">
                    <td class="fw-semibold">#{{ r.id }}</td>
                    <td>
                        <Link :href="route('admin.orders.show', r.order_id)" class="fw-semibold">{{ r.order_number ?? '—' }}</Link>
                    </td>
                    <td class="small text-muted">{{ r.customer_email ?? '—' }}</td>
                    <td class="small">{{ r.reason ?? '—' }}</td>
                    <td class="text-end">{{ r.units }} <span class="text-muted small">/ {{ r.lines }} lines</span></td>
                    <td>
                        <span v-if="r.restock" class="badge bg-label-success">Restocked</span>
                        <span v-else class="badge bg-label-warning">No restock</span>
                    </td>
                    <td class="text-nowrap text-muted small">{{ r.created_at_human ?? '—' }}</td>
                    <td class="text-end">
                        <Link :href="route('admin.returns.show', r.id)" class="btn btn-sm btn-outline-secondary">
                            View
                        </Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
