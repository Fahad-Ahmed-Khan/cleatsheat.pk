<script setup>
import { reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

const props = defineProps({
    categories: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, root: 0, with_products: 0 }) },
    parents: { type: Array, default: () => [] },
});

function destroyCategory(id) {
    if (!window.confirm('Delete this category?')) {
        return;
    }
    router.delete(route('admin.categories.destroy', id));
}

const state = reactive({
    search: props.filters?.search ?? '',
});

function applyFilters() {
    router.get(
        route('admin.categories.index'),
        {
            search: state.search || undefined,
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
    () => applyFilters(),
    { deep: true },
);
</script>

<template>
    <Head title="Admin — Categories" />
    <AdminLayout>
        <DataTable
            :paginator="categories"
            empty-title="No categories found"
            empty-description="Try adjusting your search or filters, or create a new category."
        >
            <template #toolbar>
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 w-100">
                    <div class="input-group" style="max-width: 360px;">
                        <span class="input-group-text">
                            <i class="icon-base ti tabler-search icon-18px"></i>
                        </span>
                        <input v-model="state.search" type="search" class="form-control" placeholder="Search Category" />
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                        <Link class="btn btn-primary" :href="route('admin.categories.create')">
                            <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                            Add Category
                        </Link>
                    </div>
                </div>
            </template>

            <template #head>
                <th>Category</th>
                <th>Parent</th>
                <th>Slug</th>
                <th>Products</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="c in categories.data" :key="c.id">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="avatar avatar-sm bg-label-secondary">
                                <span class="avatar-initial rounded-2">
                                    <i class="icon-base ti tabler-folder icon-18px"></i>
                                </span>
                            </span>
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-heading">{{ c.name }}</span>
                                <small class="text-muted">#{{ c.id }}</small>
                            </div>
                        </div>
                    </td>
                    <td class="text-muted">{{ c.parent?.name ?? '—' }}</td>
                    <td><code class="text-muted">{{ c.slug }}</code></td>
                    <td>{{ c.products_count }}</td>
                    <td>
                        <StatusBadge :status="c.products_count > 0 ? 'active' : 'inactive'" />
                    </td>
                    <td class="text-end">
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
                                <Link class="dropdown-item" :href="route('admin.categories.edit', c.id)">
                                    <i class="icon-base ti tabler-edit me-2"></i>
                                    Edit
                                </Link>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger" type="button" @click="destroyCategory(c.id)">
                                    <i class="icon-base ti tabler-trash me-2"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>

            <template #emptyActions>
                <Link class="btn btn-primary" :href="route('admin.categories.create')">
                    <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                    Create category
                </Link>
            </template>
        </DataTable>
    </AdminLayout>
</template>
