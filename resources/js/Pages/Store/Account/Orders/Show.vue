<script setup>
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    seo: { type: Object, required: true },
    order: { type: Object, required: true },
});

const cardClass =
    'rounded-2xl bg-stadium-white p-6 shadow-stadium ring-1 ring-stadium-outline-soft/50';
const labelClass = 'text-label text-stadium-secondary';

function formatMoney(n) {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(Number(n) || 0);
}

function formatPlacedAt(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function paymentStatusLabel(s) {
    const map = {
        pending: 'Pending',
        paid: 'Paid',
        failed: 'Failed',
        canceled: 'Canceled',
        refunded: 'Refunded',
    };
    return map[s] ?? s;
}

function itemSummary(item) {
    const parts = [item.product_name];
    if (item.variant_label) parts.push(item.variant_label);
    if (item.size_label) parts.push(`Size ${item.size_label}`);
    return parts.join(' · ');
}
</script>

<template>
    <StoreAccountLayout :seo="seo" :title="`Order ${order.order_number}`">
        <Link
            :href="route('store.account.orders.index')"
            class="text-sm font-semibold text-stadium-ink underline decoration-stadium-outline-soft underline-offset-4"
        >
            ← Back to orders
        </Link>

        <div class="mt-6 space-y-6">
            <div :class="cardClass">
                <h2 :class="labelClass">Order details</h2>
                <p class="mt-3 font-mono text-lg font-bold text-stadium-ink">{{ order.order_number }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-full bg-stadium-muted px-3 py-1 text-xs font-bold uppercase text-stadium-ink">{{ order.status }}</span>
                    <span class="rounded-full bg-store-primary/15 px-3 py-1 text-xs font-bold uppercase text-stadium-ink ring-1 ring-store-primary/30">
                        {{ paymentStatusLabel(order.payment_status) }}
                    </span>
                </div>
                <p v-if="order.placed_at" class="mt-3 text-sm text-stadium-secondary">Placed {{ formatPlacedAt(order.placed_at) }}</p>
            </div>

            <div v-if="order.items?.length" :class="cardClass">
                <h2 :class="labelClass">Products</h2>
                <ul class="mt-4 space-y-4">
                    <li
                        v-for="item in order.items"
                        :key="item.id"
                        class="flex gap-4 rounded-xl bg-stadium-muted/50 p-3 ring-1 ring-stadium-outline-soft/50"
                    >
                        <div class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-stadium-white ring-1 ring-stadium-outline-soft/80">
                            <img v-if="item.image_url" :src="item.image_url" :alt="item.product_name" class="h-full w-full object-cover" loading="lazy">
                            <div v-else class="flex h-full w-full items-center justify-center text-[10px] font-semibold uppercase text-stadium-secondary">No image</div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-stadium-ink">{{ itemSummary(item) }}</p>
                            <p class="mt-2 text-sm font-bold tabular-nums text-stadium-ink">{{ formatMoney(item.line_total) }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div v-if="order.payment || order.totals" :class="cardClass">
                <h2 :class="labelClass">Payment</h2>
                <p v-if="order.payment?.gateway_label" class="mt-3 text-sm text-stadium-ink">
                    <span class="text-stadium-secondary">Method </span>
                    <span class="font-semibold">{{ order.payment.gateway_label }}</span>
                </p>
                <ul v-if="order.payments?.length" class="mt-4 space-y-3 border-t border-stadium-outline-soft/50 pt-4">
                    <li v-for="(p, i) in order.payments" :key="i" class="flex justify-between gap-4 text-sm">
                        <span class="text-stadium-secondary">{{ p.gateway_label }} · {{ paymentStatusLabel(p.status) }}</span>
                        <span class="font-semibold tabular-nums text-stadium-ink">{{ formatMoney(p.amount) }}</span>
                    </li>
                </ul>
                <dl v-if="order.totals" class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-stadium-secondary">Grand total</dt>
                        <dd class="font-bold tabular-nums text-stadium-ink">{{ formatMoney(order.totals.grand_total) }}</dd>
                    </div>
                </dl>
            </div>

            <div v-if="order.shipping_address_snapshot" :class="cardClass">
                <h2 :class="labelClass">Ship to</h2>
                <p class="mt-3 text-sm text-stadium-ink">
                    {{ order.shipping_address_snapshot.full_name }}<br>
                    {{ order.shipping_address_snapshot.phone }}<br>
                    {{ order.shipping_address_snapshot.line1 }}<br>
                    {{ order.shipping_address_snapshot.city }}
                    <template v-if="order.shipping_address_snapshot.area">, {{ order.shipping_address_snapshot.area }}</template>
                </p>
            </div>

            <div v-if="order.shipments?.length" :class="cardClass">
                <h2 :class="labelClass">Delivery</h2>
                <div v-for="(s, i) in order.shipments" :key="i" class="mt-4 border-t border-stadium-outline-soft/50 pt-4 first:mt-2 first:border-t-0 first:pt-0">
                    <p class="text-sm font-medium text-stadium-ink">
                        {{ s.courier ?? 'Courier' }}
                        <span class="ml-2 rounded-full bg-stadium-muted px-2 py-0.5 text-xs">{{ s.status }}</span>
                    </p>
                    <p class="mt-1 font-mono text-sm text-stadium-ink">{{ s.tracking_number ?? 'Awaiting booking' }}</p>
                    <ul v-if="s.events?.length" class="mt-4 space-y-3 border-l-2 border-stadium-outline-soft pl-4">
                        <li v-for="(e, j) in s.events" :key="j" class="text-sm">
                            <span class="text-stadium-ink">{{ e.description ?? e.status }}</span>
                            <span v-if="e.occurred_at" class="mt-0.5 block text-xs text-stadium-secondary">{{ new Date(e.occurred_at).toLocaleString() }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </StoreAccountLayout>
</template>
