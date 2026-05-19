<script setup>
import { computed, reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    rows: { type: Object, required: true },
    tab: { type: String, default: 'low' },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ low: 0, out: 0 }) },
});

const state = reactive({
    search: props.filters.search ?? '',
    per_page: String(props.filters.per_page ?? 25),
});

const hasFilter = computed(() => Boolean(state.search));

function navigate(tab, extra = {}) {
    router.get(
        route('admin.inventory.low-stock'),
        {
            tab,
            search: state.search || undefined,
            per_page: state.per_page || undefined,
            ...extra,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer = null;
watch(
    () => state.search,
    () => {
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => navigate(props.tab), 250);
    },
);

watch(
    () => state.per_page,
    () => navigate(props.tab),
);

function switchTab(tab) {
    if (tab !== props.tab) {
        navigate(tab);
    }
}

function clearFilters() {
    state.search = '';
    navigate(props.tab);
}
</script>

<template>
    <Head title="Admin — Low stock" />
    <AdminLayout>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 row-gap-2">
            <div>
                <h4 class="mb-1">Inventory · Low / out of stock</h4>
                <p class="mb-0 text-muted small">Variant sizes that need attention before they break checkout.</p>
            </div>
        </div>

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: tab === 'low' }"
                    @click="switchTab('low')"
                >
                    Low stock
                    <span class="badge ms-2" :class="tab === 'low' ? 'bg-primary' : 'bg-label-secondary'">{{ stats.low }}</span>
                </button>
            </li>
            <li class="nav-item">
                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: tab === 'out' }"
                    @click="switchTab('out')"
                >
                    Out of stock
                    <span class="badge ms-2" :class="tab === 'out' ? 'bg-primary' : 'bg-label-secondary'">{{ stats.out }}</span>
                </button>
            </li>
        </ul>

        <DataTable :paginator="rows" :empty-title="tab === 'out' ? 'No out-of-stock variants' : 'No low-stock variants'" empty-description="Inventory levels look healthy on this tab.">
            <template #header>
                <div class="p-4 border-bottom">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label small text-muted">Search</label>
                            <input v-model="state.search" type="search" class="form-control" placeholder="Product name or SKU" />
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
                <th>Product</th>
                <th>SKU</th>
                <th>Color</th>
                <th>Size</th>
                <th class="text-end">Stock</th>
                <th class="text-end">Threshold</th>
                <th class="text-end">Actions</th>
            </template>

            <template #body>
                <tr v-for="r in rows.data" :key="r.id">
                    <td>
                        <Link v-if="r.product_id" :href="route('admin.products.show', r.product_id)" class="fw-semibold">
                            {{ r.product_name }}
                        </Link>
                        <span v-else class="text-muted">{{ r.product_name }}</span>
                    </td>
                    <td class="font-monospace small">{{ r.sku ?? '—' }}</td>
                    <td class="small">{{ r.color ?? '—' }}</td>
                    <td class="small">{{ r.size_label }}</td>
                    <td class="text-end fw-semibold" :class="r.stock_qty <= 0 ? 'text-danger' : 'text-warning'">{{ r.stock_qty }}</td>
                    <td class="text-end text-muted small">{{ r.low_stock_threshold }}</td>
                    <td class="text-end">
                        <Link v-if="r.product_id" :href="route('admin.products.edit', r.product_id)" class="btn btn-sm btn-outline-secondary">
                            Edit product
                        </Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
