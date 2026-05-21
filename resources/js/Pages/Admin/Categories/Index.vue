<script setup>
import { reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

const props = defineProps({
    viewMode: { type: String, default: 'tree' },
    categories: { type: Object, default: null },
    tree: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, root: 0, sub: 0, with_products: 0 }) },
    rootParents: { type: Array, default: () => [] },
});

function destroyCategory(id) {
    if (!window.confirm('Delete this category?')) {
        return;
    }
    router.delete(route('admin.categories.destroy', id));
}

const state = reactive({
    search: props.filters?.search ?? '',
    parent_id: props.filters?.parent_id ?? '',
    has_products: props.filters?.has_products ?? '',
});

function applyFilters() {
    router.get(
        route('admin.categories.index'),
        {
            search: state.search || undefined,
            parent_id: state.parent_id || undefined,
            has_products: state.has_products || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer = null;
watch(
    () => state.search,
    () => {
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => applyFilters(), 300);
    },
);

watch(
    () => [state.parent_id, state.has_products],
    () => applyFilters(),
);
</script>

<template>
    <Head title="Admin — Categories" />
    <AdminLayout>
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3 text-center text-md-start">
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-heading">{{ stats.total }}</div>
                        <small class="text-muted">Total categories</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-heading">{{ stats.root }}</div>
                        <small class="text-muted">Parent categories</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-heading">{{ stats.sub }}</div>
                        <small class="text-muted">Subcategories</small>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-heading">{{ stats.with_products }}</div>
                        <small class="text-muted">With products</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tree view -->
        <div v-if="viewMode === 'tree'" class="card">
            <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-1">Category structure</h5>
                    <p class="mb-0 text-muted small">Parent categories and their subcategories. Use search to filter the flat list.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <Link class="btn btn-primary" :href="route('admin.categories.create', { kind: 'parent' })">
                        <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                        Add parent
                    </Link>
                </div>
            </div>

            <div class="card-body border-bottom">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="filter_parent">Parent</label>
                        <select id="filter_parent" v-model="state.parent_id" class="form-select form-select-sm">
                            <option value="">All parents</option>
                            <option value="root">Parents only (no children listed)</option>
                            <option v-for="p in rootParents" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="filter_products">Products</label>
                        <select id="filter_products" v-model="state.has_products" class="form-select form-select-sm">
                            <option value="">Any</option>
                            <option value="1">Has products</option>
                            <option value="0">No products</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="filter_search">Search</label>
                        <input
                            id="filter_search"
                            v-model="state.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Name or slug…"
                        />
                    </div>
                </div>
            </div>

            <div v-if="!tree.length" class="card-body text-center py-5">
                <p class="text-muted mb-3">No categories yet. Create a parent category, then add subcategories under it.</p>
                <Link class="btn btn-primary" :href="route('admin.categories.create')">Create parent category</Link>
            </div>

            <div v-else class="list-group list-group-flush">
                <div v-for="parent in tree" :key="parent.id" class="list-group-item p-0">
                    <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 p-4 bg-light">
                        <div
                            v-if="parent.og_image_url"
                            class="flex-shrink-0 rounded overflow-hidden border"
                            style="width: 72px; height: 48px"
                        >
                            <img :src="parent.og_image_url" :alt="parent.name" class="w-100 h-100 object-fit-cover" />
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge bg-label-primary">Parent</span>
                                <span class="fw-semibold text-heading">{{ parent.name }}</span>
                                <StatusBadge :status="parent.is_active ? 'active' : 'inactive'" />
                            </div>
                            <div class="small text-muted mt-1">
                                <code>{{ parent.slug }}</code>
                                · Sort {{ parent.sort_order }}
                                · {{ parent.products_count }} products
                                · {{ parent.children.length }} subcategories
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <Link
                                class="btn btn-sm btn-outline-primary"
                                :href="route('admin.categories.create', { parent_id: parent.id })"
                            >
                                <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                                Add subcategory
                            </Link>
                            <Link class="btn btn-sm btn-outline-secondary" :href="route('admin.categories.edit', parent.id)">
                                Edit
                            </Link>
                            <button class="btn btn-sm btn-outline-danger" type="button" @click="destroyCategory(parent.id)">
                                Delete
                            </button>
                        </div>
                    </div>

                    <div v-if="parent.children.length" class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Subcategory</th>
                                    <th>Slug</th>
                                    <th>Sort</th>
                                    <th>Products</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="child in parent.children" :key="child.id">
                                    <td>
                                        <div class="d-flex align-items-center gap-2 ps-2">
                                            <img
                                                v-if="child.og_image_url"
                                                :src="child.og_image_url"
                                                :alt="child.name"
                                                class="rounded border"
                                                width="40"
                                                height="30"
                                                style="object-fit: cover"
                                            />
                                            <span class="fw-medium">{{ child.name }}</span>
                                        </div>
                                    </td>
                                    <td><code class="small">{{ child.slug }}</code></td>
                                    <td>{{ child.sort_order }}</td>
                                    <td>{{ child.products_count }}</td>
                                    <td>
                                        <StatusBadge :status="child.is_active ? 'active' : 'inactive'" />
                                    </td>
                                    <td class="text-end">
                                        <Link class="btn btn-sm btn-icon btn-outline-secondary" :href="route('admin.categories.edit', child.id)">
                                            <i class="icon-base ti tabler-edit icon-18px"></i>
                                        </Link>
                                        <button
                                            class="btn btn-sm btn-icon btn-outline-danger ms-1"
                                            type="button"
                                            @click="destroyCategory(child.id)"
                                        >
                                            <i class="icon-base ti tabler-trash icon-18px"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-4 py-3 border-top bg-white">
                        <p class="small text-muted mb-2">No subcategories yet.</p>
                        <Link
                            class="btn btn-sm btn-link p-0"
                            :href="route('admin.categories.create', { parent_id: parent.id })"
                        >
                            Add subcategory under {{ parent.name }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flat search results -->
        <DataTable
            v-else
            :paginator="categories"
            empty-title="No categories found"
            empty-description="Try adjusting your search or filters."
        >
            <template #toolbar>
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 w-100">
                    <div class="row g-2 flex-grow-1">
                        <div class="col-md-4">
                            <input v-model="state.search" type="search" class="form-control" placeholder="Search name or slug" />
                        </div>
                        <div class="col-md-3">
                            <select v-model="state.parent_id" class="form-select">
                                <option value="">All types</option>
                                <option value="root">Parents only</option>
                                <option v-for="p in rootParents" :key="p.id" :value="String(p.id)">Under: {{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select v-model="state.has_products" class="form-select">
                                <option value="">Any products</option>
                                <option value="1">Has products</option>
                                <option value="0">No products</option>
                            </select>
                        </div>
                    </div>
                    <Link class="btn btn-primary shrink-0" :href="route('admin.categories.create')">
                        <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                        Add category
                    </Link>
                </div>
            </template>

            <template #head>
                <th>Category</th>
                <th>Type</th>
                <th>Parent</th>
                <th>Slug</th>
                <th>Products</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="c in categories.data" :key="c.id">
                    <td class="fw-semibold">{{ c.name }}</td>
                    <td>
                        <span class="badge" :class="c.parent_id ? 'bg-label-info' : 'bg-label-primary'">
                            {{ c.parent_id ? 'Sub' : 'Parent' }}
                        </span>
                    </td>
                    <td class="text-muted">{{ c.parent?.name ?? '—' }}</td>
                    <td><code class="text-muted">{{ c.slug }}</code></td>
                    <td>{{ c.products_count }}</td>
                    <td>
                        <StatusBadge :status="c.is_active ? 'active' : 'inactive'" />
                    </td>
                    <td class="text-end">
                        <Link class="btn btn-sm btn-outline-secondary" :href="route('admin.categories.edit', c.id)">Edit</Link>
                        <button class="btn btn-sm btn-outline-danger ms-1" type="button" @click="destroyCategory(c.id)">
                            Delete
                        </button>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
