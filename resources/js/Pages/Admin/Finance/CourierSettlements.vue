<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    couriers: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ outstanding_count: 0, outstanding_amount: 0, settled_count: 0, settled_amount: 0 }) },
    focus_courier_id: { type: Number, default: null },
    outstanding: { type: Array, default: () => [] },
});

function money(n) {
    return new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(Number(n || 0));
}

function focusCourier(id) {
    router.get(route('admin.finance.courier-settlements'), { courier: id }, { preserveState: true, preserveScroll: true });
}

function clearFocus() {
    router.get(route('admin.finance.courier-settlements'), {}, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Admin — Courier settlements" />
    <AdminLayout>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 row-gap-2">
            <div>
                <h4 class="mb-1">Courier COD settlements</h4>
                <p class="mb-0 text-muted small">Per-courier outstanding cash-on-delivery vs. settled amounts. Delivered shipments only.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <div class="text-muted small mb-1">Outstanding orders</div>
                                <h4 class="mb-0">{{ totals.outstanding_count }}</h4>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-warning rounded">
                                    <i class="icon-base ti tabler-clock icon-26px"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <div class="text-muted small mb-1">Outstanding amount</div>
                                <h4 class="mb-0 text-warning">{{ money(totals.outstanding_amount) }}</h4>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-warning rounded">
                                    <i class="icon-base ti tabler-cash icon-26px"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <div class="text-muted small mb-1">Settled orders</div>
                                <h4 class="mb-0">{{ totals.settled_count }}</h4>
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
                                <div class="text-muted small mb-1">Settled amount</div>
                                <h4 class="mb-0 text-success">{{ money(totals.settled_amount) }}</h4>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-success rounded">
                                    <i class="icon-base ti tabler-building-bank icon-26px"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header py-2">
                <h6 class="card-title m-0">Per-courier breakdown</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Courier</th>
                            <th class="text-end">Delivered (COD)</th>
                            <th class="text-end">Outstanding orders</th>
                            <th class="text-end">Outstanding amount</th>
                            <th class="text-end">Settled orders</th>
                            <th class="text-end">Settled amount</th>
                            <th class="text-end">Discrepancies</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in couriers" :key="c.courier_id">
                            <td>
                                <div class="fw-semibold">{{ c.courier_name }}</div>
                                <div class="text-muted small">{{ c.adapter }}</div>
                            </td>
                            <td class="text-end">{{ c.delivered_count }}</td>
                            <td class="text-end fw-semibold">{{ c.outstanding_count }}</td>
                            <td class="text-end fw-semibold text-warning">{{ money(c.outstanding_amount) }}</td>
                            <td class="text-end">{{ c.settled_count }}</td>
                            <td class="text-end text-success">{{ money(c.settled_amount) }}</td>
                            <td class="text-end">
                                <span :class="c.discrepancy_count > 0 ? 'badge bg-label-danger' : 'text-muted small'">
                                    {{ c.discrepancy_count }}
                                </span>
                            </td>
                            <td class="text-end">
                                <button
                                    type="button"
                                    class="btn btn-sm"
                                    :class="focus_courier_id === c.courier_id ? 'btn-primary' : 'btn-outline-secondary'"
                                    @click="focusCourier(c.courier_id)"
                                >
                                    Outstanding orders
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!couriers.length">
                            <td colspan="8" class="text-muted small text-center py-3">No couriers configured.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="focus_courier_id" class="card">
            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h6 class="card-title m-0">Outstanding orders</h6>
                <button type="button" class="btn btn-sm btn-label-secondary" @click="clearFocus">
                    Clear selection
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Tracking</th>
                            <th class="text-end">COD amount</th>
                            <th class="text-end">Order total</th>
                            <th class="text-nowrap">Delivered at</th>
                            <th>Customer</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in outstanding" :key="row.shipment_id">
                            <td class="fw-semibold">{{ row.order_number ?? `#${row.order_id}` }}</td>
                            <td class="font-monospace small">{{ row.tracking_number ?? '—' }}</td>
                            <td class="text-end">{{ money(row.cod_amount) }}</td>
                            <td class="text-end text-muted">{{ money(row.order_total) }}</td>
                            <td class="text-nowrap text-muted small">{{ row.delivered_at ?? '—' }}</td>
                            <td class="small text-muted">{{ row.customer }}</td>
                            <td>
                                <span class="badge bg-label-warning">{{ row.order_payment_status ?? 'pending' }}</span>
                            </td>
                            <td class="text-end">
                                <Link :href="route('admin.orders.show', row.order_id)" class="btn btn-sm btn-outline-secondary">
                                    View
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!outstanding.length">
                            <td colspan="8" class="text-muted small text-center py-3">Nothing outstanding for this courier.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
