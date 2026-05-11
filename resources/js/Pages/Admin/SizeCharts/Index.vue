<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';

defineProps({
    charts: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Admin — Size charts" />
    <AdminLayout>
        <AdminPageHeader
            title="Size charts"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Size charts' }]"
        >
            <template #actions>
                <Link class="btn btn-primary btn-sm" :href="route('admin.size-charts.create')">
                    New chart
                </Link>
            </template>
        </AdminPageHeader>

        <DataTable :paginator="charts" empty-title="No size charts yet">
            <template #head>
                <th>Name</th>
                <th>Brand</th>
                <th>Rows</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="c in charts.data" :key="c.id">
                    <td class="fw-semibold">{{ c.name }}</td>
                    <td>{{ c.brand?.name }}</td>
                    <td>{{ c.rows_count }}</td>
                    <td class="text-end">
                        <Link class="btn btn-sm btn-outline-primary" :href="route('admin.size-charts.edit', c.id)">
                            Edit
                        </Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
