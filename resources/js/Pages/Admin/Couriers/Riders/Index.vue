<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import { ref } from 'vue';

const props = defineProps({
    riders: { type: Object, required: true },
    couriers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
const courierId = ref(props.filters.courier_id ?? '');

function applyFilters() {
    router.get(route('admin.riders.index'), {
        search: search.value,
        courier_id: courierId.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

const testForm = useForm({ body: '' });
const testingId = ref(null);

function sendTest(rider) {
    testingId.value = rider.id;
    testForm.post(route('admin.riders.send-test', rider.id), {
        preserveScroll: true,
        onFinish: () => { testingId.value = null; },
    });
}

function removeRider(rider) {
    if (!confirm(`Remove rider "${rider.name}"?`)) return;
    router.delete(route('admin.riders.destroy', rider.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin — Couriers riders" />
    <AdminLayout>
        <AdminPageHeader
            title="Courier riders"
            subtitle="People who pick parcels per courier company. The primary rider receives daily WhatsApp pickup notices."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Couriers', href: route('admin.couriers.index') },
                { label: 'Riders' },
            ]"
        >
            <template #actions>
                <Link :href="route('admin.riders.create')" class="btn btn-primary">
                    <i class="ti tabler-plus me-1" /> Add rider
                </Link>
            </template>
        </AdminPageHeader>

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-end">
                <div style="flex: 1 1 200px;">
                    <label class="form-label">Search</label>
                    <input v-model="search" type="search" class="form-control" placeholder="Name or phone…" @keyup.enter="applyFilters" />
                </div>
                <div style="flex: 0 0 220px;">
                    <label class="form-label">Courier</label>
                    <select v-model="courierId" class="form-select" @change="applyFilters">
                        <option value="">All couriers</option>
                        <option v-for="c in couriers" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <button type="button" class="btn btn-outline-primary" @click="applyFilters">Apply</button>
            </div>
        </div>

        <DataTable :paginator="riders" empty-title="No riders added yet">
            <template #head>
                <th>Name</th>
                <th>Courier</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Primary?</th>
                <th>Quick test</th>
                <th></th>
            </template>
            <template #body>
                <tr v-for="r in riders.data" :key="r.id">
                    <td>
                        <div class="fw-semibold">{{ r.name }}</div>
                        <div v-if="r.notes" class="text-muted small" style="max-width: 280px;">{{ r.notes }}</div>
                    </td>
                    <td>{{ r.courier?.name ?? '—' }}</td>
                    <td>
                        <code>{{ r.phone }}</code>
                        <div v-if="r.alt_phone" class="text-muted small">Alt: <code>{{ r.alt_phone }}</code></div>
                    </td>
                    <td><StatusBadge :status="r.is_active ? 'active' : 'inactive'" /></td>
                    <td><StatusBadge v-if="r.is_primary" status="primary" /><span v-else class="text-muted">—</span></td>
                    <td>
                        <div class="d-flex gap-1">
                            <input v-model="testForm.body" class="form-control form-control-sm" placeholder="(default test message)" style="max-width: 220px;" />
                            <button type="button" class="btn btn-sm btn-outline-primary" :disabled="testingId === r.id" @click="sendTest(r)">
                                {{ testingId === r.id ? '…' : 'Send' }}
                            </button>
                        </div>
                    </td>
                    <td class="text-end">
                        <Link :href="route('admin.riders.edit', r.id)" class="btn btn-sm btn-outline-secondary">Edit</Link>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-1" @click="removeRider(r)">Remove</button>
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
