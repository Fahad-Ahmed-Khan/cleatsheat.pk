<script setup>
import { computed, reactive, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import { confirmDanger, toastError, toastSuccess } from '@/admin/swalToast';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({ total: 0, failed: 0, whatsapp_failed: 0, sent_24h: 0 }) },
    facets: { type: Object, default: () => ({ channels: [], statuses: [], template_keys: [] }) },
});

const state = reactive({
    channel: props.filters.channel ?? '',
    status: props.filters.status ?? '',
    template_key: props.filters.template_key ?? '',
    recipient: props.filters.recipient ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    preset: props.filters.preset ?? '',
    per_page: String(props.filters.per_page ?? 25),
});

const hasAnyFilter = computed(() => Boolean(
    state.channel
        || state.status
        || state.template_key
        || state.recipient
        || state.date_from
        || state.date_to
        || state.preset,
));

function applyFilters() {
    router.get(
        route('admin.notifications.index'),
        {
            channel: state.channel || undefined,
            status: state.status || undefined,
            template_key: state.template_key || undefined,
            recipient: state.recipient || undefined,
            date_from: state.date_from || undefined,
            date_to: state.date_to || undefined,
            preset: state.preset || undefined,
            per_page: state.per_page || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

let searchTimer = null;
watch(
    () => state.recipient,
    () => {
        if (searchTimer) window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => applyFilters(), 300);
    },
);

watch(
    () => [
        state.channel,
        state.status,
        state.template_key,
        state.date_from,
        state.date_to,
        state.preset,
        state.per_page,
    ],
    () => applyFilters(),
);

function clearFilters() {
    state.channel = '';
    state.status = '';
    state.template_key = '';
    state.recipient = '';
    state.date_from = '';
    state.date_to = '';
    state.preset = '';
    applyFilters();
}

function togglePreset(key) {
    state.preset = state.preset === key ? '' : key;
}

const expandedPayload = ref(new Set());
function togglePayload(id) {
    const next = new Set(expandedPayload.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    expandedPayload.value = next;
}

const retryInFlight = ref(new Set());

async function retry(log) {
    if (!log.is_retryable) return;
    const ok = await confirmDanger({
        title: 'Resend this WhatsApp notification?',
        text: `Recipient: ${log.recipient}. Template: ${log.template_key}. A new log row will be written; the original failure stays in the history.`,
        confirmText: 'Yes, resend',
    });
    if (!ok) return;

    const next = new Set(retryInFlight.value);
    next.add(log.id);
    retryInFlight.value = next;

    router.post(route('admin.notifications.retry', log.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toastSuccess('WhatsApp notification re-sent');
            router.reload({ preserveScroll: true });
        },
        onError: () => toastError('Retry failed'),
        onFinish: () => {
            const after = new Set(retryInFlight.value);
            after.delete(log.id);
            retryInFlight.value = after;
        },
    });
}

function statusBadgeClass(status) {
    const map = {
        sent: 'bg-label-success',
        failed: 'bg-label-danger',
        pending: 'bg-label-warning',
        skipped: 'bg-label-secondary',
    };
    return map[status] ?? 'bg-label-secondary';
}
</script>

<template>
    <Head title="Admin — Notifications" />
    <AdminLayout>
        <!-- KPI tiles -->
        <div class="card mb-4">
            <div class="card-body card-widget-separator">
                <div class="row gy-4 gy-sm-1">
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.total ?? 0 }}</h4>
                                <p class="mb-0">All notifications</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-secondary rounded">
                                    <i class="icon-base ti tabler-bell icon-26px text-heading"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none me-6" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.failed ?? 0 }}</h4>
                                <p class="mb-0">Failed (all channels)</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-danger rounded">
                                    <i class="icon-base ti tabler-alert-circle icon-26px"></i>
                                </span>
                            </span>
                        </div>
                        <hr class="d-none d-sm-block d-lg-none" />
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start border-end pb-4 pb-sm-0">
                            <div>
                                <h4 class="mb-0">{{ stats.whatsapp_failed ?? 0 }}</h4>
                                <p class="mb-0">WhatsApp DLQ</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-warning rounded">
                                    <i class="icon-base ti tabler-brand-whatsapp icon-26px"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-0">{{ stats.sent_24h ?? 0 }}</h4>
                                <p class="mb-0">Sent in last 24h</p>
                            </div>
                            <span class="avatar">
                                <span class="avatar-initial bg-label-success rounded">
                                    <i class="icon-base ti tabler-check icon-26px"></i>
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <DataTable :paginator="logs" empty-title="No notifications" empty-description="Outbound notifications will appear here once your store starts dispatching them.">
            <template #header>
                <div class="p-4 border-bottom">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h5 class="card-title mb-0">Filter</h5>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="state.preset === 'failed' ? 'btn-primary' : 'btn-label-secondary'"
                                @click="togglePreset('failed')"
                            >
                                Failed only
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="state.preset === 'whatsapp_failed' ? 'btn-primary' : 'btn-label-secondary'"
                                @click="togglePreset('whatsapp_failed')"
                            >
                                WhatsApp DLQ
                            </button>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Channel</label>
                            <select v-model="state.channel" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in facets.channels" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Status</label>
                            <select v-model="state.status" class="form-select">
                                <option value="">All</option>
                                <option v-for="s in facets.statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Template</label>
                            <select v-model="state.template_key" class="form-select">
                                <option value="">All</option>
                                <option v-for="t in facets.template_keys" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Recipient</label>
                            <input v-model="state.recipient" type="search" class="form-control" placeholder="Phone, email, …" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">From</label>
                            <input v-model="state.date_from" type="date" class="form-control" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">To</label>
                            <input v-model="state.date_to" type="date" class="form-control" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small text-muted">Per page</label>
                            <select v-model="state.per_page" class="form-select">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3 d-flex align-items-end justify-content-end">
                            <button v-if="hasAnyFilter" type="button" class="btn btn-label-secondary" @click="clearFilters">
                                <i class="icon-base ti tabler-x icon-18px me-1"></i>
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <template #head>
                <th class="text-nowrap">Sent</th>
                <th>Channel</th>
                <th>Template</th>
                <th>Recipient</th>
                <th>Status</th>
                <th>Error</th>
                <th class="text-end">Actions</th>
            </template>

            <template #body>
                <template v-for="log in logs.data" :key="log.id">
                    <tr>
                        <td class="text-muted small text-nowrap">{{ log.created_at_human ?? '—' }}</td>
                        <td>
                            <span class="badge bg-label-secondary">{{ log.channel }}</span>
                        </td>
                        <td class="small font-monospace">{{ log.template_key }}</td>
                        <td class="small font-monospace text-truncate" style="max-width: 220px;">{{ log.recipient }}</td>
                        <td>
                            <span class="badge" :class="statusBadgeClass(log.status)">{{ log.status }}</span>
                        </td>
                        <td class="small text-danger text-truncate" style="max-width: 240px;">{{ log.error_message ?? '' }}</td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <button
                                    v-if="log.payload"
                                    type="button"
                                    class="btn btn-sm btn-outline-secondary"
                                    @click="togglePayload(log.id)"
                                >
                                    {{ expandedPayload.has(log.id) ? 'Hide payload' : 'Payload' }}
                                </button>
                                <button
                                    v-if="log.is_retryable"
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    :disabled="retryInFlight.has(log.id)"
                                    @click="retry(log)"
                                >
                                    {{ retryInFlight.has(log.id) ? 'Retrying…' : 'Retry' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="expandedPayload.has(log.id)">
                        <td colspan="7" class="bg-body-tertiary">
                            <pre class="small mb-0 text-body" style="white-space: pre-wrap; word-break: break-word;">{{ JSON.stringify(log.payload, null, 2) }}</pre>
                        </td>
                    </tr>
                </template>
            </template>
        </DataTable>
    </AdminLayout>
</template>
