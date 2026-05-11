<script setup>
import { computed, reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, active: 0, inactive: 0 }) },
    brands: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
    sizes: { type: Array, default: () => [] },
});

const state = reactive({
    search: props.filters?.search ?? '',
    brand_id: props.filters?.brand_id ?? '',
    category_id: props.filters?.category_id ?? '',
    status: props.filters?.status ?? '',
    stock: props.filters?.stock ?? '',
    color_id: props.filters?.color_id ?? '',
    size: props.filters?.size ?? '',
    price_min: props.filters?.price_min ?? '',
    price_max: props.filters?.price_max ?? '',
    per_page: String(props.filters?.per_page ?? 20),
});

const hasAnyFilter = computed(() => {
    return Boolean(
        state.search ||
            state.brand_id ||
            state.category_id ||
            state.status ||
            state.stock ||
            state.color_id ||
            state.size ||
            state.price_min ||
            state.price_max,
    );
});

function applyFilters() {
    router.get(
        route('admin.products.index'),
        {
            search: state.search || undefined,
            brand_id: state.brand_id || undefined,
            category_id: state.category_id || undefined,
            status: state.status || undefined,
            stock: state.stock || undefined,
            color_id: state.color_id || undefined,
            size: state.size || undefined,
            price_min: state.price_min || undefined,
            price_max: state.price_max || undefined,
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
    () => [
        state.brand_id,
        state.category_id,
        state.status,
        state.stock,
        state.color_id,
        state.size,
        state.price_min,
        state.price_max,
        state.per_page,
    ],
    () => applyFilters(),
);

function clearFilters() {
    state.search = '';
    state.brand_id = '';
    state.category_id = '';
    state.status = '';
    state.stock = '';
    state.color_id = '';
    state.size = '';
    state.price_min = '';
    state.price_max = '';
    state.per_page = String(props.filters?.per_page ?? 20);
    applyFilters();
}

function toggleActive(product) {
    router.patch(
        route('admin.products.toggle-active', product.id),
        { is_active: !product.is_active },
        { preserveScroll: true },
    );
}

function destroyProduct(product) {
    if (!window.confirm('Delete this product?')) {
        return;
    }
    router.delete(route('admin.products.destroy', product.id), { preserveScroll: true });
}

function formatMoney(v) {
    const n = Number(v);
    if (!Number.isFinite(n)) return '—';
    return n.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}

function categoryPill(categoryName) {
    const raw = String(categoryName || '').toLowerCase();
    if (!raw) return { cls: 'bg-label-secondary', icon: 'tabler-tag' };
    if (raw.includes('sg') || raw.includes('soft')) return { cls: 'bg-label-success', icon: 'tabler-home-2' };
    if (raw.includes('tf') || raw.includes('turf')) return { cls: 'bg-label-info', icon: 'tabler-leaf' };
    if (raw.includes('fg') || raw.includes('firm')) return { cls: 'bg-label-warning', icon: 'tabler-mountain' };
    if (raw.includes('ic') || raw.includes('indoor') || raw.includes('futsal')) return { cls: 'bg-label-primary', icon: 'tabler-building-store' };
    if (raw.includes('ag') || raw.includes('artificial')) return { cls: 'bg-label-danger', icon: 'tabler-plant' };
    return { cls: 'bg-label-secondary', icon: 'tabler-tag' };
}

const expanded = reactive(new Set());
const variantCache = reactive({});
const variantLoading = reactive(new Set());
const variantError = reactive({});

function isExpanded(productId) {
    return expanded.has(productId);
}

function stopRowToggle(e) {
    const el = e?.target;
    if (!el || !(el instanceof Element)) return false;
    return Boolean(el.closest('a,button,input,select,textarea,label,.dropdown-menu,.dropdown-toggle'));
}

async function ensureVariantsLoaded(productId) {
    if (variantCache[productId] || variantLoading.has(productId)) return;
    variantLoading.add(productId);
    variantError[productId] = '';
    try {
        const res = await fetch(route('admin.products.variants', productId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error(`Failed (${res.status})`);
        }
        const json = await res.json();
        variantCache[productId] = json;
    } catch (err) {
        variantError[productId] = err?.message || 'Failed to load variants';
    } finally {
        variantLoading.delete(productId);
    }
}

async function toggleRow(productId, e) {
    if (stopRowToggle(e)) return;
    if (expanded.has(productId)) {
        expanded.delete(productId);
        return;
    }
    expanded.add(productId);
    await ensureVariantsLoaded(productId);
}
</script>

<template>
    <Head title="Admin — Products" />
    <AdminLayout>
        <!-- KPI cards (theme-style) -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Total products</div>
                            <div class="h4 mb-0">{{ stats.total ?? products.total ?? 0 }}</div>
                        </div>
                        <span class="avatar bg-label-secondary">
                            <span class="avatar-initial rounded">
                                <i class="icon-base ti tabler-package icon-26px text-heading"></i>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Active</div>
                            <div class="h4 mb-0">{{ stats.active ?? 0 }}</div>
                        </div>
                        <span class="avatar bg-label-success">
                            <span class="avatar-initial rounded">
                                <i class="icon-base ti tabler-circle-check icon-26px text-heading"></i>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small">Inactive</div>
                            <div class="h4 mb-0">{{ stats.inactive ?? 0 }}</div>
                        </div>
                        <span class="avatar bg-label-danger">
                            <span class="avatar-initial rounded">
                                <i class="icon-base ti tabler-circle-x icon-26px text-heading"></i>
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <DataTable
            :paginator="products"
            empty-title="No products found"
            empty-description="Try adjusting your search or filters, or create a new product."
        >
            <!-- Theme-like header: Filter row + toolbar row -->
            <template #header>
                <div class="p-4 border-bottom">
                    <h5 class="card-title mb-3">Filter</h5>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <select v-model="state.status" class="form-select">
                                <option value="">Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <select v-model="state.category_id" class="form-select">
                                <option value="">Category</option>
                                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <select v-model="state.stock" class="form-select">
                                <option value="">Stock</option>
                                <option value="in">In stock</option>
                                <option value="out">Out of stock</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <select v-model="state.color_id" class="form-select">
                                <option value="">Color</option>
                                <option v-for="c in colors" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <select v-model="state.size" class="form-select">
                                <option value="">Size</option>
                                <option v-for="s in sizes" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="input-group">
                                <span class="input-group-text">PKR</span>
                                <input v-model="state.price_min" type="number" min="0" class="form-control" placeholder="Min" />
                                <input v-model="state.price_max" type="number" min="0" class="form-control" placeholder="Max" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div class="input-group" style="max-width: 360px;">
                            <span class="input-group-text">
                                <i class="icon-base ti tabler-search icon-18px"></i>
                            </span>
                            <input v-model="state.search" type="search" class="form-control" placeholder="Search Product" />
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                            <select v-model="state.per_page" class="form-select" style="width: 90px;">
                                <option value="7">7</option>
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>

                            <button type="button" class="btn btn-label-secondary" disabled>
                                <i class="icon-base ti tabler-upload icon-xs me-1"></i>
                                Export
                            </button>

                            <Link class="btn btn-primary" :href="route('admin.products.create')">
                                <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                                Add Product
                            </Link>

                            <button v-if="hasAnyFilter" type="button" class="btn btn-label-secondary" @click="clearFilters">
                                <i class="icon-base ti tabler-x icon-18px me-1"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th>Product</th>
                <th>Category</th>
                <th class="text-nowrap">Price</th>
                <th>Stock</th>
                <th>Sizes</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <template v-for="p in products.data" :key="p.id">
                <tr
                    role="button"
                    class="cursor-pointer"
                    @click="(e) => toggleRow(p.id, e)"
                >
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm bg-label-secondary">
                                <span class="avatar-initial rounded-2">
                                    <i class="icon-base ti tabler-box icon-18px"></i>
                                </span>
                            </span>
                            <div class="d-flex flex-column">
                                <Link
                                    class="fw-semibold text-heading"
                                    :href="route('admin.products.show', p.id)"
                                >
                                    {{ p.name }}
                                </Link>
                                <small class="text-muted d-flex flex-wrap gap-2">
                                    <span>#{{ p.id }}</span>
                                    <span v-if="p.primary_sku" class="font-monospace">SKU: {{ p.primary_sku }}</span>
                                </small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span v-if="p.category?.name" class="badge" :class="categoryPill(p.category.name).cls">
                            <i class="icon-base ti" :class="categoryPill(p.category.name).icon + ' icon-14px me-1'"></i>
                            {{ p.category.name }}
                        </span>
                        <span v-else class="text-muted">—</span>
                    </td>
                    <td class="text-nowrap">
                        <span v-if="p.min_price != null && p.max_price != null">
                            <span v-if="Number(p.min_price) === Number(p.max_price)">
                                <span class="fw-semibold">{{ formatMoney(p.min_price) }}</span>
                            </span>
                            <span v-else>
                                <span class="fw-semibold">{{ formatMoney(p.min_price) }}–{{ formatMoney(p.max_price) }}</span>
                            </span>
                            <small class="text-muted ms-1">PKR</small>
                        </span>
                        <span v-else class="text-muted">—</span>
                    </td>
                    <td>
                        <span class="badge" :class="(p.stock_total ?? 0) > 0 ? 'bg-label-success' : 'bg-label-warning'">
                            {{ p.stock_total ?? 0 }}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-label-secondary">
                            {{ p.sizes_count ?? 0 }}
                        </span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <label class="switch switch-primary switch-sm mb-0">
                                <input
                                    type="checkbox"
                                    class="switch-input"
                                    :checked="p.is_active"
                                    @change="() => toggleActive(p)"
                                />
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                            </label>
                        </div>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex align-items-center justify-content-end gap-1">
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    <i class="icon-base ti tabler-dots-vertical icon-18px"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <Link class="dropdown-item" :href="route('admin.products.edit', p.id)">
                                        <i class="icon-base ti tabler-edit me-2"></i>
                                        Edit
                                    </Link>
                                    <button class="dropdown-item" type="button" @click="() => toggleActive(p)">
                                        <i class="icon-base ti tabler-switch-2 me-2"></i>
                                        {{ p.is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button class="dropdown-item text-danger" type="button" @click="() => destroyProduct(p)">
                                        <i class="icon-base ti tabler-trash me-2"></i>
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <tr v-if="isExpanded(p.id)" class="bg-body-tertiary">
                    <td colspan="7" class="p-0">
                        <div class="p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div class="fw-semibold">Variants & stock</div>
                                <button type="button" class="btn btn-sm btn-label-secondary" @click.stop="expanded.delete(p.id)">
                                    <i class="icon-base ti tabler-chevron-up icon-18px me-1"></i>
                                    Collapse
                                </button>
                            </div>

                            <div v-if="variantLoading.has(p.id)" class="text-muted small">
                                Loading…
                            </div>
                            <div v-else-if="variantError[p.id]" class="alert alert-danger mb-0">
                                {{ variantError[p.id] }}
                            </div>
                            <div v-else class="table-responsive">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Variant</th>
                                            <th>SKU</th>
                                            <th class="text-nowrap">Price</th>
                                            <th>Sizes / stock</th>
                                            <th class="text-end">Total stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="v in (variantCache[p.id]?.variants || [])" :key="v.id">
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span
                                                        v-if="v.color?.hex"
                                                        class="rounded-circle border"
                                                        :style="{ width: '12px', height: '12px', backgroundColor: v.color.hex }"
                                                    ></span>
                                                    <span class="fw-semibold">{{ v.color?.name ?? 'Variant' }}</span>
                                                    <span v-if="!v.is_active" class="badge bg-label-warning">inactive</span>
                                                </div>
                                            </td>
                                            <td class="font-monospace text-muted">{{ v.sku }}</td>
                                            <td class="text-nowrap">
                                                {{ formatMoney(v.price) }} <small class="text-muted">PKR</small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <span
                                                        v-for="s in (v.sizes || [])"
                                                        :key="s.id"
                                                        class="badge"
                                                        :class="s.stock_qty > 0 ? 'bg-label-success' : 'bg-label-secondary'"
                                                    >
                                                        {{ s.size_label }}: {{ s.stock_qty }}
                                                    </span>
                                                    <span v-if="(v.sizes || []).length === 0" class="text-muted small">No sizes</span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge" :class="(v.stock_total ?? 0) > 0 ? 'bg-label-success' : 'bg-label-warning'">
                                                    {{ v.stock_total ?? 0 }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="(variantCache[p.id]?.variants || []).length === 0">
                                            <td colspan="5" class="text-muted small">No variants.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </td>
                </tr>
                </template>
            </template>

            <template #emptyActions>
                <Link class="btn btn-primary" :href="route('admin.products.create')">
                    <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                    Create product
                </Link>
            </template>
        </DataTable>
    </AdminLayout>
</template>
