<script setup>
import { reactive, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';

const props = defineProps({
    brands: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

function destroyBrand(id) {
    if (!window.confirm('Delete this brand?')) {
        return;
    }
    router.delete(route('admin.brands.destroy', id));
}

const state = reactive({
    search: props.filters?.search ?? '',
});

let searchTimer = null;
watch(
    () => state.search,
    () => {
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            router.get(route('admin.brands.index'), { search: state.search || undefined }, { preserveState: true, preserveScroll: true, replace: true });
        }, 250);
    },
);
</script>

<template>
    <Head title="Admin — Brands" />
    <AdminLayout>
        <DataTable :paginator="brands" empty-title="No brands yet" empty-description="Create your first brand to start organizing products.">
            <template #header>
                <div class="p-4 border-bottom">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <div class="input-group" style="max-width: 360px;">
                            <span class="input-group-text">
                                <i class="icon-base ti tabler-search icon-18px"></i>
                            </span>
                            <input v-model="state.search" type="search" class="form-control" placeholder="Search Brand" />
                        </div>

                        <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                            <Link class="btn btn-primary" :href="route('admin.brands.create')">
                                <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                                Add Brand
                            </Link>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th>Logo</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Products</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="b in brands.data" :key="b.id">
                    <td>
                        <div class="avatar avatar-sm bg-label-secondary rounded">
                            <img v-if="b.logo_url" :src="b.logo_url" :alt="b.name" class="rounded w-100 h-100 object-fit-cover" />
                            <span v-else class="avatar-initial rounded">
                                <i class="icon-base ti tabler-photo icon-18px"></i>
                            </span>
                        </div>
                    </td>
                    <td class="fw-semibold">{{ b.name }}</td>
                    <td><code>{{ b.slug }}</code></td>
                    <td>{{ b.products_count }}</td>
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
                                <Link class="dropdown-item" :href="route('admin.brands.edit', b.id)">
                                    <i class="icon-base ti tabler-edit me-2"></i>
                                    Edit
                                </Link>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item text-danger" type="button" @click="destroyBrand(b.id)">
                                    <i class="icon-base ti tabler-trash me-2"></i>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>

            <template #emptyActions>
                <Link class="btn btn-primary" :href="route('admin.brands.create')">
                    <i class="icon-base ti tabler-plus icon-xs me-1"></i>
                    Create brand
                </Link>
            </template>
        </DataTable>
    </AdminLayout>
</template>
