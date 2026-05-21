<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';
import { ref } from 'vue';

const props = defineProps({
    tally: { type: Array, required: true },
    date: { type: String, required: true },
});

const localDate = ref(props.date);

function changeDate() {
    router.get(route('admin.pickups.index'), { date: localDate.value }, {
        preserveScroll: true,
        replace: true,
    });
}

const sendForm = useForm({ courier_id: null, date: props.date });
const sendingId = ref(null);

function sendNotice(row) {
    if (!row.rider) return;
    sendingId.value = row.courier.id;
    sendForm.courier_id = row.courier.id;
    sendForm.date = localDate.value;
    sendForm.post(route('admin.pickups.send'), {
        preserveScroll: true,
        onFinish: () => { sendingId.value = null; },
    });
}
</script>

<template>
    <Head title="Admin — Daily pickups" />
    <AdminLayout>
        <AdminPageHeader
            title="Daily pickups"
            subtitle="Outstanding parcels booked today per courier. Send the pickup notice to each courier's primary rider manually, or wait for the scheduled run."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Logistics' },
                { label: 'Daily pickups' },
            ]"
        >
            <template #actions>
                <Link :href="route('admin.riders.index')" class="btn btn-outline-secondary">
                    Manage riders
                </Link>
            </template>
        </AdminPageHeader>

        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-3 align-items-end">
                <div>
                    <label class="form-label">Date</label>
                    <input v-model="localDate" type="date" class="form-control" @change="changeDate" />
                </div>
                <div class="text-muted small ms-auto">
                    Shipments included: <code>status = booked</code> AND <code>booked_at::date = {{ localDate }}</code>.
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div v-for="row in tally" :key="row.courier.id" class="col-12 col-md-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-0">{{ row.courier.name }}</h5>
                            <div class="text-muted small">{{ row.courier.code }}</div>
                        </div>
                        <span class="badge bg-label-primary">{{ row.parcel_count }} parcel(s)</span>
                    </div>
                    <div class="card-body">
                        <div v-if="row.rider" class="mb-3">
                            <div class="text-muted small">Primary rider</div>
                            <div class="fw-semibold">{{ row.rider.name }}</div>
                            <div><code>{{ row.rider.phone }}</code></div>
                        </div>
                        <div v-else class="alert alert-warning small mb-3">
                            No active primary rider for this courier.
                            <Link :href="route('admin.riders.create')" class="alert-link">Add one</Link>.
                        </div>

                        <div class="text-muted small">COD total</div>
                        <div class="mb-3">PKR {{ row.cod_total.toLocaleString() }}</div>

                        <div v-if="row.tracking_numbers.length" class="mb-3">
                            <div class="text-muted small">Tracking numbers</div>
                            <div class="d-flex flex-wrap gap-1">
                                <code v-for="t in row.tracking_numbers" :key="t" class="text-nowrap">{{ t }}</code>
                            </div>
                        </div>

                        <div v-if="row.dispatches.length" class="mb-3">
                            <div class="text-muted small mb-1">Already sent today</div>
                            <ul class="list-unstyled small mb-0">
                                <li v-for="d in row.dispatches" :key="d.id" class="d-flex justify-content-between border-bottom py-1">
                                    <span>
                                        <StatusBadge :status="d.status" class="me-1" />
                                        <span class="text-muted">{{ d.sent_via }}</span>
                                        — {{ d.rider?.name ?? '—' }}
                                    </span>
                                    <span class="text-muted">{{ d.sent_at ?? '—' }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="!row.rider || row.parcel_count === 0 || sendingId === row.courier.id"
                            @click="sendNotice(row)"
                        >
                            {{ sendingId === row.courier.id ? 'Sending…' : 'Send pickup notice' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
