<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import StatusBadge from '@/Components/Admin/StatusBadge.vue';

defineProps({
    coupons: {
        type: Object,
        required: true,
    },
});

function formatType(type) {
    if (type === 'percent') return 'Percent';
    if (type === 'fixed') return 'Fixed';
    return type;
}

function formatValue(c) {
    if (c.type === 'percent') {
        return `${Number(c.value)}%`;
    }
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(Number(c.value));
}

function formatMinCart(c) {
    if (c.min_cart_total == null || c.min_cart_total === '') return '—';
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(Number(c.min_cart_total));
}

function formatValidity(c) {
    const s = c.starts_at;
    const e = c.ends_at;
    if (!s && !e) return '—';
    const opts = { month: 'short', day: 'numeric', year: 'numeric' };
    const a = s ? new Date(s).toLocaleDateString('en-PK', opts) : '…';
    const b = e ? new Date(e).toLocaleDateString('en-PK', opts) : '…';
    return `${a} – ${b}`;
}

function formatUsage(c) {
    const cap = c.max_redemptions != null ? c.max_redemptions : '∞';
    return `${c.redemptions_count ?? 0} / ${cap}`;
}
</script>

<template>
    <Head title="Admin — Coupons" />
    <AdminLayout>
        <AdminPageHeader
            title="Coupons"
            subtitle="Promo codes and cart discounts."
            :breadcrumbs="[{ label: 'Admin', href: route('admin.dashboard') }, { label: 'Coupons' }]"
        >
            <template #actions>
                <Link class="btn btn-primary" :href="route('admin.coupons.create')">
                    <i class="icon-base ti tabler-plus icon-18px me-1"></i>
                    Create coupon
                </Link>
            </template>
        </AdminPageHeader>

        <DataTable :paginator="coupons" empty-title="No coupons yet" empty-description="Create a coupon to offer discounts at checkout.">
            <template #emptyActions>
                <Link class="btn btn-sm btn-primary" :href="route('admin.coupons.create')">Create coupon</Link>
            </template>
            <template #head>
                <th>Code</th>
                <th>Type</th>
                <th>Value</th>
                <th>Min cart</th>
                <th>Validity</th>
                <th>Uses</th>
                <th>Active</th>
            </template>
            <template #body>
                <tr v-for="c in coupons.data" :key="c.id">
                    <td class="fw-semibold"><code>{{ c.code }}</code></td>
                    <td>{{ formatType(c.type) }}</td>
                    <td>{{ formatValue(c) }}</td>
                    <td class="text-muted small">{{ formatMinCart(c) }}</td>
                    <td class="text-muted small text-nowrap">{{ formatValidity(c) }}</td>
                    <td class="text-muted small font-monospace">{{ formatUsage(c) }}</td>
                    <td>
                        <StatusBadge :status="c.is_active ? 'active' : 'inactive'" />
                    </td>
                </tr>
            </template>
        </DataTable>
    </AdminLayout>
</template>
