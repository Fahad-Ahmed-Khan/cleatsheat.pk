<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import { ref } from 'vue';

const props = defineProps({
    conversations: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    thread_messages: { type: Array, default: () => [] },
});

const search = ref(props.filters.search ?? '');

function applySearch() {
    router.get(route('admin.whatsapp-inbox.index'), { search: search.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openThread(phone) {
    router.get(route('admin.whatsapp-inbox.index'), { search: search.value, thread: phone }, {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Admin — WhatsApp inbox" />
    <AdminLayout>
        <AdminPageHeader
            title="WhatsApp inbox"
            subtitle="Inbound messages grouped by customer phone. Button replies for COD confirmation appear here."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Inbox' },
            ]"
        />

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-body d-flex gap-2">
                        <input v-model="search" type="search" class="form-control" placeholder="Search phone or message…" @keyup.enter="applySearch" />
                        <button type="button" class="btn btn-outline-primary" @click="applySearch">Search</button>
                    </div>
                </div>

                <DataTable :paginator="conversations">
                    <template #head>
                        <th>From</th>
                        <th>Last message</th>
                        <th></th>
                    </template>
                    <template #body>
                        <tr
                            v-for="c in conversations.data"
                            :key="c.from_number"
                            :class="{ 'table-active': filters.thread === c.from_number }"
                            role="button"
                            @click="openThread(c.from_number)"
                        >
                            <td class="font-monospace small">{{ c.from_number }}</td>
                            <td class="small text-truncate" style="max-width: 200px;">{{ c.last_body ?? '—' }}</td>
                            <td class="text-end">
                                <span v-if="c.order_number" class="badge bg-label-info">{{ c.order_number }}</span>
                            </td>
                        </tr>
                    </template>
                </DataTable>
            </div>

            <div class="col-lg-7">
                <div class="card min-vh-50">
                    <div class="card-header py-2">
                        <h6 class="card-title m-0">
                            Thread
                            <span v-if="filters.thread" class="font-monospace text-muted ms-2">{{ filters.thread }}</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div v-if="!filters.thread" class="text-muted">Select a conversation to read messages.</div>
                        <div v-else class="d-flex flex-column gap-3">
                            <div
                                v-for="m in thread_messages"
                                :key="m.id"
                                class="border rounded p-2"
                            >
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>{{ m.type }} · {{ m.handled_as ?? '—' }}</span>
                                    <span>{{ m.received_at ? new Date(m.received_at).toLocaleString() : '' }}</span>
                                </div>
                                <div>{{ m.body ?? m.button_payload ?? '—' }}</div>
                                <div v-if="m.order_id" class="mt-2">
                                    <Link :href="route('admin.orders.show', m.order_id)" class="btn btn-sm btn-link p-0">View order #{{ m.order_id }}</Link>
                                </div>
                                <div v-if="m.handler_notes" class="small text-muted mt-1">{{ m.handler_notes }}</div>
                            </div>
                            <div v-if="!thread_messages.length" class="text-muted small">No messages in this thread.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
