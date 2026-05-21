<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminPageHeader from '@/Components/Admin/AdminPageHeader.vue';
import WhatsAppSendPanel from '@/Components/Admin/WhatsAppSendPanel.vue';

const props = defineProps({
    customer: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    whatsapp_templates: { type: Array, default: () => [] },
    whatsapp_send_route: { type: String, required: true },
});

function money(n) {
    return new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(Number(n || 0));
}
</script>

<template>
    <Head :title="`Customer — ${customer.name ?? customer.email}`" />
    <AdminLayout>
        <AdminPageHeader
            :title="customer.name ?? 'Customer'"
            :subtitle="customer.email"
            :breadcrumbs="[
                { label: 'Admin', href: route('admin.dashboard') },
                { label: 'Customers', href: route('admin.customers.index') },
                { label: customer.name ?? 'Profile' },
            ]"
        />

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header py-2"><h6 class="card-title m-0">Details</h6></div>
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-sm-3">Phone</dt>
                            <dd class="col-sm-9 font-monospace">{{ customer.phone ?? '—' }}</dd>
                            <dt class="col-sm-3">WhatsApp opt-out</dt>
                            <dd class="col-sm-9">
                                <span v-if="customer.whatsapp_opted_out" class="badge bg-label-danger">Opted out</span>
                                <span v-else class="badge bg-label-success">Can receive marketing</span>
                            </dd>
                            <dt class="col-sm-3">Joined</dt>
                            <dd class="col-sm-9">{{ customer.created_at }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header py-2"><h6 class="card-title m-0">Recent orders</h6></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Status</th>
                                    <th class="text-end">Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in orders" :key="o.id">
                                    <td>{{ o.order_number }}</td>
                                    <td><span class="badge bg-label-secondary">{{ o.status }}</span></td>
                                    <td class="text-end">{{ money(o.grand_total) }}</td>
                                    <td class="text-end">
                                        <Link :href="route('admin.orders.show', o.id)" class="btn btn-sm btn-link">Open</Link>
                                    </td>
                                </tr>
                                <tr v-if="!orders.length">
                                    <td colspan="4" class="text-muted text-center py-3">No orders yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <WhatsAppSendPanel
                    :send-route="whatsapp_send_route"
                    :templates="whatsapp_templates"
                    recipient-label="Customer phone"
                    :recipient-phone="customer.phone"
                />
            </div>
        </div>
    </AdminLayout>
</template>
