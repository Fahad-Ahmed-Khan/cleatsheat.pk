<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

defineProps({
    couriers: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Admin — Couriers" />
    <AdminLayout>
        <AdminPageHeader
            title="Couriers"
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Couriers' }]"
        />

        <DataTable :paginator="couriers" empty-title="No couriers found">
            <template #head>
                <th>Code</th>
                <th>Name</th>
                <th>Active</th>
            </template>
            <template #body>
                <tr v-for="c in couriers.data" :key="c.id">
                    <td><code>{{ c.code }}</code></td>
                    <td>{{ c.name }}</td>
                    <td><StatusBadge :status="c.is_active ? 'active' : 'inactive'" /></td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
