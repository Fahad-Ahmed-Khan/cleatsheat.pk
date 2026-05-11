<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';

defineProps({
    session: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
});

function money(amount) {
    if (amount == null || amount === '') return '—';
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(Number(amount));
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('en-PK', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function outcomeBadgeClass(kind) {
    if (kind === 'success') return 'bg-label-success';
    if (kind === 'danger') return 'bg-label-danger';
    if (kind === 'warning') return 'bg-label-warning';
    return 'bg-label-secondary';
}

function roleLabel(role) {
    const r = String(role || '').toLowerCase();
    if (r === 'user' || r === 'customer') return 'Customer';
    if (r === 'assistant' || r === 'bot' || r === 'system') return 'Assistant';
    return role || '—';
}

function isCustomerRole(role) {
    const r = String(role || '').toLowerCase();
    return r === 'user' || r === 'customer';
}
</script>

<template>
    <Head :title="`Bargain #${session.id}`" />
    <AdminLayout>
        <AdminPageHeader
            :title="`Bargain #${session.id}`"
            subtitle="Message history for this negotiation."
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Bargaining', href: route('admin.bargaining.index') },
                { label: `#${session.id}` },
            ]"
        >
            <template #actions>
                <Link class="btn btn-sm btn-label-secondary" :href="route('admin.bargaining.index')">Back to list</Link>
            </template>
        </AdminPageHeader>

        <div class="card mb-4">
            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h6 class="mb-0">Summary</h6>
                <span class="badge" :class="outcomeBadgeClass(session.outcome_kind)">{{ session.outcome }}</span>
            </div>
            <div class="card-body py-3">
                <div class="row g-3 small">
                    <div class="col-12 col-md-6">
                        <div class="text-muted">Product</div>
                        <div class="fw-semibold">{{ session.product_name ?? '—' }}</div>
                        <div><code>{{ session.sku ?? '—' }}</code> <span v-if="session.variant_label" class="text-muted">· {{ session.variant_label }}</span></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="text-muted">Customer</div>
                        <div>{{ session.customer_name || '—' }}</div>
                        <div class="text-muted">{{ session.customer_phone || session.user_email || '—' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">List price</div>
                        <div>{{ money(session.list_price) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Current offer</div>
                        <div>{{ money(session.current_offer) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Accepted price</div>
                        <div>{{ money(session.accepted_price) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted">Expires</div>
                        <div>{{ formatWhen(session.expires_at) }}</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="text-muted">Raw state</div>
                        <code>{{ session.state }}</code>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="text-muted">Lock used at checkout</div>
                        <div>{{ formatWhen(session.lock_consumed_at) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-2">
                <h6 class="mb-0">Messages ({{ messages.length }})</h6>
            </div>
            <div class="card-body p-0">
                <div
                    v-if="messages.length"
                    class="bargain-chat-thread px-3 py-3 bg-body-tertiary"
                    style="max-height: min(70vh, 520px); overflow-y: auto;"
                >
                    <div
                        v-for="m in messages"
                        :key="m.id"
                        class="d-flex mb-3"
                        :class="isCustomerRole(m.role) ? 'justify-content-end' : 'justify-content-start'"
                    >
                        <div
                            class="shadow-sm rounded-4 px-3 py-2"
                            :class="
                                isCustomerRole(m.role)
                                    ? 'bg-primary text-white'
                                    : 'border bg-body text-body'
                            "
                            style="max-width: min(92%, 420px);"
                        >
                            <div
                                class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-1 small"
                            >
                                <span class="fw-semibold" :class="isCustomerRole(m.role) ? 'text-white' : ''">{{ roleLabel(m.role) }}</span>
                                <span
                                    class="text-nowrap"
                                    :class="isCustomerRole(m.role) ? 'text-white-50' : 'text-muted'"
                                    style="font-size: 11px;"
                                >{{ formatWhen(m.created_at) }}</span>
                            </div>
                            <div
                                class="small"
                                :class="isCustomerRole(m.role) ? 'text-white' : 'text-body'"
                                style="white-space: pre-wrap; word-break: break-word;"
                            >{{ m.body }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="p-4 text-muted small">No messages recorded.</div>
            </div>
        </div>
    </AdminLayout>
</template>
