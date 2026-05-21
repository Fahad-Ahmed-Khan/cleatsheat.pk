<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

const props = defineProps({
    campaign: { type: Object, required: true },
    stats: { type: Object, required: true },
    recent_recipients: { type: Array, default: () => [] },
});

function sendNow() {
    if (!confirm('Start sending this campaign now?')) return;
    router.post(route('admin.whatsapp-campaigns.send', props.campaign.id), {}, { preserveScroll: true });
}

function cancelCampaign() {
    if (!confirm('Cancel this campaign?')) return;
    router.post(route('admin.whatsapp-campaigns.cancel', props.campaign.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Campaign — ${campaign.name}`" />
    <AdminLayout>
        <AdminPageHeader
            :title="campaign.name"
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Campaigns', href: route('admin.whatsapp-campaigns.index') },
                { label: campaign.name },
            ]"
        >
            <template #actions>
                <Link
                    v-if="['draft', 'scheduled'].includes(campaign.status)"
                    :href="route('admin.whatsapp-campaigns.edit', campaign.id)"
                    class="btn btn-outline-primary"
                >Edit</Link>
                <button
                    v-if="['draft', 'scheduled'].includes(campaign.status)"
                    type="button"
                    class="btn btn-success"
                    @click="sendNow"
                >Send now</button>
                <button
                    v-if="['draft', 'scheduled', 'sending'].includes(campaign.status)"
                    type="button"
                    class="btn btn-outline-danger"
                    @click="cancelCampaign"
                >Cancel</button>
            </template>
        </AdminPageHeader>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card"><div class="card-body py-3">
                    <div class="text-muted small">Status</div>
                    <StatusBadge :label="campaign.status" tone="info" />
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body py-3">
                    <div class="text-muted small">Sent</div>
                    <div class="h5 mb-0">{{ campaign.sent_count }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body py-3">
                    <div class="text-muted small">Failed</div>
                    <div class="h5 mb-0 text-danger">{{ campaign.failed_count }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card"><div class="card-body py-3">
                    <div class="text-muted small">Pending</div>
                    <div class="h5 mb-0">{{ stats.pending }}</div>
                </div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2"><h6 class="card-title m-0">Recent recipients</h6></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Phone</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Sent at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in recent_recipients" :key="r.id">
                            <td class="font-monospace small">{{ r.phone }}</td>
                            <td>{{ r.name ?? '—' }}</td>
                            <td><span class="badge bg-label-secondary">{{ r.status }}</span></td>
                            <td class="small text-muted">{{ r.sent_at ? new Date(r.sent_at).toLocaleString() : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
