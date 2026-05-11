<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
import StatCard from '@/Components/Admin/StatCard.vue';

const props = defineProps({
    counts: { type: Object, required: true },
    kpis: { type: Object, required: true },
    charts: { type: Object, required: true },
    recent_orders: { type: Array, default: () => [] },
    top_products: { type: Array, default: () => [] },
    logistics: { type: Array, default: () => [] },
});

const logTab = ref('new');

const filteredLogistics = computed(() => (props.logistics || []).filter((s) => s.tab === logTab.value));

const monthLabel = computed(() =>
    new Date().toLocaleString('en-PK', { month: 'long', year: 'numeric' }),
);

function money(n) {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(Number(n));
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-PK', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const palette = ['#7367f0', '#28c76f', '#00cfe8', '#ff9f43', '#82868b', '#ea5455', '#6610f2'];

const salesChartOptions = computed(() => ({
    chart: {
        type: 'line',
        toolbar: { show: false },
        zoom: { enabled: false },
        fontFamily: 'inherit',
        dropShadow: { enabled: false },
    },
    stroke: { curve: 'smooth', width: [3, 2] },
    dataLabels: { enabled: false },
    colors: ['#7367f0', '#ff9f43'],
    markers: { size: 0, hover: { size: 5 } },
    xaxis: {
        categories: props.charts?.sales_daily?.categories ?? [],
        labels: { style: { fontSize: '11px' } },
    },
    yaxis: {
        labels: {
            formatter: (v) => (v >= 1000 ? `Rs ${Math.round(v / 1000)}k` : `Rs ${Math.round(v)}`),
        },
    },
    legend: { position: 'top', horizontalAlign: 'left', fontSize: '13px' },
    grid: { borderColor: 'rgba(var(--bs-border-color-rgb), 0.35)', strokeDashArray: 4 },
    tooltip: {
        y: {
            formatter: (v) => money(v),
        },
    },
}));

const salesSeries = computed(() => [
    { name: 'Order total (GMV)', data: props.charts?.sales_daily?.revenue ?? [] },
    { name: 'Shipping + COD (customer)', data: props.charts?.sales_daily?.fulfillment_fees ?? [] },
]);

const ordersDonutOptions = computed(() => ({
    chart: { fontFamily: 'inherit' },
    labels: props.charts?.orders_by_status?.labels ?? [],
    colors: palette,
    legend: { position: 'bottom', fontSize: '12px' },
    dataLabels: { enabled: true, formatter: (val) => `${Math.round(val)}%` },
    plotOptions: {
        pie: {
            donut: {
                size: '68%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Orders',
                        formatter: () =>
                            String((props.charts?.orders_by_status?.series ?? []).reduce((a, b) => a + b, 0)),
                    },
                },
            },
        },
    },
}));

const ordersDonutSeries = computed(() => props.charts?.orders_by_status?.series ?? []);

const shipmentsBarOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: {
        bar: { borderRadius: 6, horizontal: false, columnWidth: '52%' },
    },
    dataLabels: { enabled: false },
    colors: ['#7367f0'],
    xaxis: {
        categories: props.charts?.shipments_by_status?.labels ?? [],
        labels: { style: { fontSize: '11px' } },
    },
    yaxis: { labels: { formatter: (v) => String(Math.round(v)) } },
    grid: { borderColor: 'rgba(var(--bs-border-color-rgb), 0.35)', strokeDashArray: 4 },
}));

const shipmentsBarSeries = computed(() => [
    { name: 'Shipments', data: props.charts?.shipments_by_status?.series ?? [] },
]);

const logTabCounts = computed(() => {
    const L = props.logistics || [];
    return {
        new: L.filter((s) => s.tab === 'new').length,
        shipping: L.filter((s) => s.tab === 'shipping').length,
        done: L.filter((s) => s.tab === 'done').length,
    };
});
</script>

<template>
    <Head title="Admin — Overview" />
    <AdminLayout>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="mb-1">Dashboard</h4>
                <p class="text-muted mb-0 small">Sales, stock, and logistics — {{ monthLabel }}</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <Link class="btn btn-sm btn-primary" :href="route('admin.orders.index')">
                    <i class="icon-base ti tabler-shopping-cart icon-16px me-1"></i>
                    Orders
                </Link>
                <Link class="btn btn-sm btn-label-secondary" :href="route('admin.products.index')">
                    <i class="icon-base ti tabler-package icon-16px me-1"></i>
                    Products
                </Link>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-5 col-xl-4">
                <div class="card h-100 border-0 bg-primary text-white shadow-sm">
                    <div class="card-body d-flex flex-column justify-content-center py-4">
                        <span class="text-white text-opacity-75 small text-uppercase fw-semibold" style="letter-spacing: 0.06em;">Performance</span>
                        <h3 class="text-white mt-2 mb-1">Store overview</h3>
                        <p class="text-white text-opacity-75 small mb-3">
                            Track paid revenue, order volume, fulfillment fees, and shipment pipeline.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <Link class="btn btn-sm btn-light" :href="route('admin.orders.index')">View sales</Link>
                            <Link class="btn btn-sm btn-outline-light" :href="route('admin.shipping-settings.edit')">Shipping</Link>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7 col-xl-8">
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="card h-100 mb-0">
                            <div class="card-body py-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="avatar avatar-sm rounded bg-label-primary">
                                        <i class="icon-base ti tabler-currency-rupee icon-20px"></i>
                                    </span>
                                    <span class="text-muted small">Paid revenue</span>
                                </div>
                                <div class="h5 mb-0">{{ money(kpis.revenue_paid_mtd) }}</div>
                                <div class="text-muted mt-1" style="font-size: 11px;">MTD</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card h-100 mb-0">
                            <div class="card-body py-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="avatar avatar-sm rounded bg-label-success">
                                        <i class="icon-base ti tabler-receipt icon-20px"></i>
                                    </span>
                                    <span class="text-muted small">GMV (MTD)</span>
                                </div>
                                <div class="h5 mb-0">{{ money(kpis.gmv_mtd) }}</div>
                                <div class="text-muted mt-1" style="font-size: 11px;">Non-cancelled</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card h-100 mb-0">
                            <div class="card-body py-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="avatar avatar-sm rounded bg-label-info">
                                        <i class="icon-base ti tabler-shopping-bag icon-20px"></i>
                                    </span>
                                    <span class="text-muted small">Orders</span>
                                </div>
                                <div class="h5 mb-0">{{ kpis.orders_mtd }}</div>
                                <div class="text-muted mt-1" style="font-size: 11px;">{{ kpis.paid_orders_mtd }} paid</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card h-100 mb-0">
                            <div class="card-body py-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <span class="avatar avatar-sm rounded bg-label-warning">
                                        <i class="icon-base ti tabler-chart-arrows icon-20px"></i>
                                    </span>
                                    <span class="text-muted small">Avg. order</span>
                                </div>
                                <div class="h5 mb-0">{{ money(kpis.avg_order_value) }}</div>
                                <div class="text-muted mt-1" style="font-size: 11px;">MTD</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Stock units</div>
                        <div class="h5 mb-0">{{ kpis.stock_total_units.toLocaleString() }}</div>
                        <div class="text-muted mt-2 small">Across all variant sizes</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Low stock SKUs</div>
                        <div class="h5 mb-0 text-warning">{{ kpis.low_stock_variants }}</div>
                        <div class="text-muted mt-2 small">At or below threshold</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Out of stock</div>
                        <div class="h5 mb-0 text-danger">{{ kpis.out_of_stock_variants }}</div>
                        <div class="text-muted mt-2 small">Zero quantity</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body py-3">
                        <div class="text-muted small mb-1">Shipments</div>
                        <div class="h5 mb-0">{{ kpis.shipments_pending_booked }}</div>
                        <div class="text-muted mt-2 small">{{ kpis.shipments_in_transit }} in transit</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card h-100 mb-0">
                    <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-0">Sales &amp; fees</h5>
                            <div class="text-muted small">Last 30 days — GMV vs shipping + COD charged on orders</div>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <VueApexCharts type="line" height="320" :options="salesChartOptions" :series="salesSeries" />
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card h-100 mb-0">
                    <div class="card-header py-3">
                        <h5 class="mb-0">Orders by status</h5>
                        <div class="text-muted small">All time</div>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center pt-0">
                        <VueApexCharts
                            v-if="ordersDonutSeries.length"
                            type="donut"
                            height="320"
                            width="100%"
                            :options="ordersDonutOptions"
                            :series="ordersDonutSeries"
                        />
                        <div v-else class="text-muted small py-5 text-center">No order data yet.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header py-3">
                        <h5 class="mb-0">Shipments by status</h5>
                        <div class="text-muted small">Logistics volume</div>
                    </div>
                    <div class="card-body pt-0">
                        <VueApexCharts
                            v-if="(charts.shipments_by_status?.series ?? []).length"
                            type="bar"
                            height="300"
                            :options="shipmentsBarOptions"
                            :series="shipmentsBarSeries"
                        />
                        <div v-else class="text-muted small py-5 text-center">No shipments yet.</div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100 mb-0">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Popular products</h5>
                            <div class="text-muted small">Units sold · last 30 days</div>
                        </div>
                        <Link class="small" :href="route('admin.products.index')">Catalog</Link>
                    </div>
                    <div class="card-body p-0">
                        <ul v-if="top_products.length" class="list-group list-group-flush">
                            <li v-for="(p, idx) in top_products" :key="idx" class="list-group-item d-flex justify-content-between align-items-center py-2">
                                <div class="me-2 min-w-0">
                                    <div class="fw-semibold small text-truncate">{{ p.product_name }}</div>
                                    <code class="small text-muted">{{ p.sku }}</code>
                                </div>
                                <span class="badge bg-label-primary">{{ p.qty_sold }}</span>
                            </li>
                        </ul>
                        <div v-else class="p-4 text-muted small">No sales in the last 30 days.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-7">
                <div class="card h-100 mb-0">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0">Recent orders</h5>
                        <Link class="btn btn-sm btn-outline-primary" :href="route('admin.orders.index')">View all</Link>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in recent_orders" :key="o.id">
                                    <td>
                                        <Link class="fw-semibold text-body" :href="route('admin.orders.show', o.id)">{{ o.order_number }}</Link>
                                    </td>
                                    <td><span class="badge bg-label-secondary">{{ o.status_label }}</span></td>
                                    <td class="small text-muted">{{ o.payment_status }}</td>
                                    <td class="text-end">{{ money(o.grand_total) }}</td>
                                    <td class="text-end text-muted small text-nowrap">{{ formatWhen(o.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card h-100 mb-0">
                    <div class="card-header py-3">
                        <h5 class="mb-0">Logistics</h5>
                        <div class="text-muted small">Recent shipments</div>
                    </div>
                    <div class="card-body pt-0">
                        <ul class="nav nav-tabs nav-fill mb-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    type="button"
                                    class="nav-link"
                                    :class="{ active: logTab === 'new' }"
                                    @click="logTab = 'new'"
                                >
                                    New
                                    <span class="badge bg-label-secondary ms-1">{{ logTabCounts.new }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    type="button"
                                    class="nav-link"
                                    :class="{ active: logTab === 'shipping' }"
                                    @click="logTab = 'shipping'"
                                >
                                    Shipping
                                    <span class="badge bg-label-secondary ms-1">{{ logTabCounts.shipping }}</span>
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    type="button"
                                    class="nav-link"
                                    :class="{ active: logTab === 'done' }"
                                    @click="logTab = 'done'"
                                >
                                    Done
                                    <span class="badge bg-label-secondary ms-1">{{ logTabCounts.done }}</span>
                                </button>
                            </li>
                        </ul>
                        <ul v-if="filteredLogistics.length" class="list-unstyled mb-0" style="max-height: 320px; overflow-y: auto;">
                            <li
                                v-for="s in filteredLogistics"
                                :key="s.id"
                                class="border rounded p-2 mb-2 bg-body-tertiary"
                            >
                                <div class="d-flex justify-content-between gap-2">
                                    <Link v-if="s.order_id" class="fw-semibold small" :href="route('admin.orders.show', s.order_id)">{{ s.order_number }}</Link>
                                    <span v-else class="fw-semibold small">{{ s.order_number }}</span>
                                    <span class="badge bg-label-primary">{{ s.status_label }}</span>
                                </div>
                                <div class="text-muted small mt-1">{{ s.courier_name }}</div>
                                <code class="small d-block text-truncate">{{ s.tracking_number || '—' }}</code>
                                <div class="text-muted mt-1" style="font-size: 11px;">{{ formatWhen(s.updated_at) }}</div>
                            </li>
                        </ul>
                        <div v-else class="text-muted small">No shipments in this tab.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-3">
                <h5 class="mb-0">Catalog overview</h5>
                <div class="text-muted small">Quick links to manage the storefront</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-sm-4 col-lg-2">
                        <StatCard title="Products" :value="counts.products" :href="route('admin.products.index')" />
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <StatCard title="Orders" :value="counts.orders" :href="route('admin.orders.index')" />
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <StatCard title="Brands" :value="counts.brands" :href="route('admin.brands.index')" />
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <StatCard title="Categories" :value="counts.categories" :href="route('admin.categories.index')" />
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <StatCard title="Couriers" :value="counts.couriers" :href="route('admin.couriers.index')" />
                    </div>
                    <div class="col-6 col-sm-4 col-lg-2">
                        <StatCard title="Coupons" :value="counts.coupons" :href="route('admin.coupons.index')" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
