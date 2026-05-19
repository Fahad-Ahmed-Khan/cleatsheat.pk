<script setup>
import { computed, reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    events: { type: Object, required: true },
    couriers: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const state = reactive({
    courier_id: props.filters.courier_id ?? '',
    status: props.filters.status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    per_page: String(props.filters.per_page ?? 50),
});

const hasFilter = computed(() => Boolean(
    state.courier_id || state.status || state.date_from || state.date_to,
));

function applyFilters() {
    router.get(
        route('admin.logistics.timeline'),
        {
            courier_id: state.courier_id || undefined,
            status: state.status || undefined,
            date_from: state.date_from || undefined,
            date_to: state.date_to || undefined,
            per_page: state.per_page || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(
    () => [state.courier_id, state.status, state.date_from, state.date_to, state.per_page],
    () => applyFilters(),
);

function clearFilters() {
    state.courier_id = '';
    state.status = '';
    state.date_from = '';
    state.date_to = '';
    applyFilters();
}

function statusClass(s) {
    const map = {
        pending: 'bg-label-secondary',
        booked: 'bg-label-info',
        in_transit: 'bg-label-primary',
        delivered: 'bg-label-success',
        failed: 'bg-label-danger',
        canceled: 'bg-label-warning',
    };
    return `badge ${map[s] ?? 'bg-label-secondary'}`;
}
</script>

<template>
    <Head title="Admin — Shipment timeline" />
    <AdminLayout>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 row-gap-2">
            <div>
                <h4 class="mb-1">Shipment timeline</h4>
                <p class="mb-0 text-muted small">Cross-order stream of every shipment event, newest first. Useful for spotting carrier-wide issues.</p>
            </div>
        </div>

        <DataTable :paginator="events" empty-title="No shipment events" empty-description="Adjust filters or wait for the next tracking webhook.">
            <template #header>
                <div class="p-4 border-bottom">
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Courier</label>
                            <select v-model="state.courier_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Status</label>
                            <select v-model="state.status" class="form-select">
                                <option value="">All</option>
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
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
                            <select v-model="state.per_page" class="form-select" style="width: 100px;">
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                            </select>
                            <button v-if="hasFilter" type="button" class="btn btn-label-secondary" @click="clearFilters">
                                <i class="icon-base ti tabler-x icon-18px me-1"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th class="text-nowrap">When</th>
                <th>Order</th>
                <th>Courier</th>
                <th>Tracking</th>
                <th>Status</th>
                <th>Description</th>
            </template>

            <template #body>
                <tr v-for="e in events.data" :key="e.id">
                    <td class="text-nowrap text-muted small">{{ e.occurred_at_human ?? '—' }}</td>
                    <td>
                        <Link v-if="e.order_id" :href="route('admin.orders.show', e.order_id)" class="fw-semibold">
                            {{ e.order_number ?? `#${e.order_id}` }}
                        </Link>
                        <span v-else class="text-muted small">—</span>
                    </td>
                    <td>
                        <div class="small fw-semibold">{{ e.courier_name ?? '—' }}</div>
                        <div class="text-muted small">{{ e.courier_adapter ?? '' }}</div>
                    </td>
                    <td class="font-monospace small">{{ e.tracking_number ?? '—' }}</td>
                    <td>
                        <span :class="statusClass(e.status)">{{ e.status }}</span>
                    </td>
                    <td class="small">{{ e.description ?? '—' }}</td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
