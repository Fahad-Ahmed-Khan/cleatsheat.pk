<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';

defineProps({
    colors: { type: Object, required: true },
});

function destroyColor(id) {
    if (!window.confirm('Delete this color?')) {
        return;
    }
    router.delete(route('admin.colors.destroy', id));
}
</script>

<template>
    <Head title="Admin — Colors" />
    <AdminLayout>
        <AdminPageHeader
            title="Colors"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Colors' }]"
        >
            <template #actions>
                <Link class="btn btn-primary btn-sm" :href="route('admin.colors.create')">
                    New color
                </Link>
            </template>
        </AdminPageHeader>

        <DataTable :paginator="colors" empty-title="No colors yet">
            <template #head>
                <th style="width: 80px">Swatch</th>
                <th>Name</th>
                <th>Slug</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="c in colors.data" :key="c.id">
                    <td>
                        <span
                            class="d-inline-block rounded-circle border"
                            :style="{ width: '28px', height: '28px', backgroundColor: c.hex }"
                        />
                    </td>
                    <td class="fw-semibold">{{ c.name }}</td>
                    <td><code>{{ c.slug }}</code></td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Color actions">
                            <Link class="btn btn-outline-primary" :href="route('admin.colors.edit', c.id)">
                                Edit
                            </Link>
                            <button type="button" class="btn btn-outline-danger" @click="destroyColor(c.id)">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
