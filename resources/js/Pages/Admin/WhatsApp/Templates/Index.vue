<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import { ref } from 'vue';

const props = defineProps({
    templates: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    cloud_enabled: { type: Boolean, default: false },
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get(route('admin.whatsapp-templates.index'), { search: search.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const testForm = useForm({ recipient: '' });
const syncAllForm = useForm({ force: false });
const testingId = ref(null);
const syncingId = ref(null);

function sendTest(template) {
    if (!testForm.recipient) return;
    testingId.value = template.id;
    testForm.post(route('admin.whatsapp-templates.send-test', template.id), {
        preserveScroll: true,
        onFinish: () => { testingId.value = null; },
    });
}

function syncAll(force = false) {
    if (!props.cloud_enabled) return;
    const msg = force
        ? 'Force re-sync ALL active templates? Approved Meta templates will be deleted and recreated — each requires Meta re-approval.'
        : 'Push all active templates to Meta? New submissions require Meta approval.';
    if (!confirm(msg)) return;
    syncAllForm.force = force;
    syncAllForm.post(route('admin.whatsapp-templates.sync-meta-all'), { preserveScroll: true });
}

function needsForceResync(template) {
    const err = String(template.meta_sync_error ?? '');
    return err.includes('approved and differs');
}

function syncOne(template, force = false) {
    if (!props.cloud_enabled) return;
    const msg = force
        ? 'Force re-sync this template? Approved Meta templates will be deleted and recreated.'
        : 'Push this template to Meta? New submissions require Meta approval.';
    if (!confirm(msg)) return;
    syncingId.value = template.id;
    router.post(route('admin.whatsapp-templates.sync-meta', template.id), { force }, {
        preserveScroll: true,
        onFinish: () => { syncingId.value = null; },
    });
}

function destroyTemplate(template) {
    if (template.is_system) return;
    if (!confirm(`Delete template "${template.label}"? This cannot be undone.`)) return;
    router.delete(route('admin.whatsapp-templates.destroy', template.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Admin — WhatsApp templates" />
    <AdminLayout>
        <AdminPageHeader
            title="WhatsApp templates"
            subtitle="Edit message copy without redeploying. System templates can be edited but not deleted."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'WhatsApp templates' },
            ]"
        >
            <template #actions>
                <button
                    v-if="cloud_enabled"
                    type="button"
                    class="btn btn-outline-primary me-2"
                    :disabled="syncAllForm.processing"
                    @click="syncAll(false)"
                >
                    {{ syncAllForm.processing ? 'Syncing…' : 'Sync all to Meta' }}
                </button>
                <button
                    v-if="cloud_enabled"
                    type="button"
                    class="btn btn-outline-warning me-2"
                    :disabled="syncAllForm.processing"
                    title="Delete and recreate approved templates that differ from local copy"
                    @click="syncAll(true)"
                >
                    Force sync all
                </button>
                <Link :href="route('admin.whatsapp-templates.create')" class="btn btn-primary">
                    <i class="ti tabler-plus me-1" /> New template
                </Link>
            </template>
        </AdminPageHeader>

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-end">
                <div class="flex-grow-1">
                    <label class="form-label">Search</label>
                    <input
                        v-model="search"
                        type="search"
                        class="form-control"
                        placeholder="Search by key, label, or body…"
                        @keyup.enter="applySearch"
                    />
                </div>
                <button type="button" class="btn btn-outline-primary" @click="applySearch">
                    Search
                </button>
            </div>
        </div>

        <div class="card mb-3 border-info bg-label-info">
            <div class="card-body small">
                <strong>Available placeholders:</strong>
                <code>{name}</code>, <code>{order}</code>, <code>{total}</code>, <code>{status}</code>, <code>{payment}</code>, <code>{phone}</code>, <code>{city}</code>,
                <code>{courier}</code>, <code>{tracking_number}</code>, <code>{tracking_url}</code>, <code>{review_url}</code>, <code>{order_url}</code>.
                Rider templates also support <code>{parcels}</code>, <code>{cod_total}</code>, <code>{tracking_list}</code>.
                Reply buttons use <code>{order_id}</code> in payload IDs; link button URLs may end with <code>{order_number}</code>.
                <span v-if="cloud_enabled" class="d-block mt-2">
                    After editing copy, use <strong>Sync to Meta</strong> or run <code>php artisan whatsapp:sync-templates</code>.
                    If Meta says the approved template differs, use <strong>Force</strong> to delete and recreate (requires Meta re-approval).
                    Interactive button templates are not synced (they use session messages).
                </span>
                <span v-else class="d-block mt-2 text-warning">
                    Enable Cloud API (<code>WHATSAPP_CLOUD_ENABLED=true</code>) to sync templates to Meta.
                </span>
            </div>
        </div>

        <DataTable :paginator="templates" empty-title="No templates yet">
            <template #head>
                <th>Key</th>
                <th>Audience</th>
                <th>Category</th>
                <th>Preview</th>
                <th>Meta</th>
                <th>Active</th>
                <th>Test send</th>
                <th></th>
            </template>
            <template #body>
                <tr v-for="t in templates.data" :key="t.id">
                    <td>
                        <div class="fw-semibold">
                            <code>{{ t.key }}</code>
                            <span v-if="t.is_system" class="badge bg-label-secondary ms-1" title="System template — cannot be deleted">sys</span>
                        </div>
                        <div class="text-muted small">{{ t.label }}</div>
                    </td>
                    <td><StatusBadge :status="t.audience" /></td>
                    <td>{{ t.category }}</td>
                    <td>
                        <div class="text-muted small" style="max-width: 320px; white-space: pre-wrap;">{{ t.body.slice(0, 120) }}<span v-if="t.body.length > 120">…</span></div>
                        <div v-if="t.has_buttons" class="mt-1">
                            <span v-for="b in t.button_payloads" :key="b.id" class="badge bg-label-primary me-1">{{ b.title }}</span>
                        </div>
                    </td>
                    <td>
                        <div v-if="t.cloud_template_name" class="small font-monospace">{{ t.cloud_template_name }}</div>
                        <div v-else class="text-muted small">(uses key)</div>
                        <div v-if="t.meta_sync_status" class="mt-1">
                            <StatusBadge :status="t.meta_sync_status" />
                        </div>
                        <div v-if="t.meta_last_synced_at" class="text-muted small">{{ t.meta_last_synced_at }}</div>
                        <div v-if="t.meta_sync_error" class="text-danger small">{{ t.meta_sync_error }}</div>
                    </td>
                    <td><StatusBadge :status="t.is_active ? 'active' : 'inactive'" /></td>
                    <td>
                        <div class="d-flex gap-1" v-if="t.audience !== 'admin'">
                            <input
                                v-model="testForm.recipient"
                                class="form-control form-control-sm"
                                placeholder="03001234567"
                                style="max-width: 160px;"
                            />
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                :disabled="testingId === t.id || !testForm.recipient"
                                @click="sendTest(t)"
                            >
                                {{ testingId === t.id ? '…' : 'Send' }}
                            </button>
                        </div>
                        <div v-else class="text-muted small">N/A</div>
                    </td>
                    <td class="text-end text-nowrap">
                        <template v-if="cloud_enabled && !t.has_buttons">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary me-1"
                                :disabled="syncingId === t.id"
                                title="Sync to Meta"
                                @click="syncOne(t, false)"
                            >
                                {{ syncingId === t.id ? '…' : 'Sync' }}
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm me-1"
                                :class="needsForceResync(t) ? 'btn-warning' : 'btn-outline-warning'"
                                :disabled="syncingId === t.id"
                                title="Delete and recreate approved template (requires Meta re-approval)"
                                @click="syncOne(t, true)"
                            >
                                Force
                            </button>
                        </template>
                        <Link :href="route('admin.whatsapp-templates.edit', t.id)" class="btn btn-sm btn-outline-secondary">
                            Edit
                        </Link>
                        <button
                            v-if="!t.is_system"
                            type="button"
                            class="btn btn-sm btn-outline-danger ms-1"
                            @click="destroyTemplate(t)"
                        >
                            Delete
                        </button>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
