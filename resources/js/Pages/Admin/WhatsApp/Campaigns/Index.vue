<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

defineProps({
    campaigns: { type: Object, required: true },
});

function statusTone(status) {
    if (status === 'completed') return 'success';
    if (status === 'sending') return 'info';
    if (status === 'cancelled') return 'secondary';
    if (status === 'scheduled') return 'warning';
    return 'primary';
}
</script>

<template>
    <Head title="Admin — WhatsApp campaigns" />
    <AdminLayout>
        <AdminPageHeader
            title="WhatsApp campaigns"
            subtitle="Bulk promotional messages with throttling and opt-out respect."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Campaigns' },
            ]"
        >
            <template #actions>
                <Link :href="route('admin.whatsapp-campaigns.create')" class="btn btn-primary">
                    <i class="ti tabler-plus me-1" /> New campaign
                </Link>
            </template>
        </AdminPageHeader>

        <DataTable :paginator="campaigns">
            <template #head>
                <th>Name</th>
                <th>Template</th>
                <th>Status</th>
                <th class="text-end">Sent</th>
                <th class="text-end">Failed</th>
                <th class="text-nowrap">Scheduled</th>
                <th class="text-end">Actions</th>
            </template>
            <template #body>
                <tr v-for="c in campaigns.data" :key="c.id">
                    <td class="fw-semibold">{{ c.name }}</td>
                    <td class="small text-muted">{{ c.template_label }}</td>
                    <td><StatusBadge :label="c.status" :tone="statusTone(c.status)" /></td>
                    <td class="text-end">{{ c.sent_count }}</td>
                    <td class="text-end">{{ c.failed_count }}</td>
                    <td class="small text-muted">{{ c.scheduled_for ? new Date(c.scheduled_for).toLocaleString() : '—' }}</td>
                    <td class="text-end">
                        <Link :href="route('admin.whatsapp-campaigns.show', c.id)" class="btn btn-sm btn-outline-primary">Open</Link>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
