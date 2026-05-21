<script setup>
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    seo: { type: Object, required: true },
    recentOrder: { type: Object, default: null },
    ordersCount: { type: Number, default: 0 },
});

const cardClass =
    'rounded-2xl bg-stadium-white p-6 shadow-stadium ring-1 ring-stadium-outline-soft/50';

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
    });
}
</script>

<template>
    <StoreAccountLayout :seo="seo" title="My account">
        <div class="grid gap-6 sm:grid-cols-2">
            <div :class="cardClass">
                <h2 class="text-label text-stadium-secondary">Orders</h2>
                <p class="mt-2 text-3xl font-bold text-stadium-ink">{{ ordersCount }}</p>
                <Link
                    :href="route('store.account.orders.index')"
                    class="mt-4 inline-block text-sm font-semibold text-store-primary underline underline-offset-4"
                >
                    View order history
                </Link>
            </div>
            <div :class="cardClass">
                <h2 class="text-label text-stadium-secondary">Wishlist</h2>
                <p class="mt-2 text-sm text-stadium-secondary">Products you saved for later.</p>
                <Link
                    :href="route('store.account.wishlist')"
                    class="mt-4 inline-block text-sm font-semibold text-store-primary underline underline-offset-4"
                >
                    Open wishlist
                </Link>
            </div>
        </div>

        <section v-if="recentOrder" class="mt-8" :class="cardClass">
            <h2 class="text-label text-stadium-secondary">Latest order</h2>
            <p class="mt-3 font-mono text-lg font-bold text-stadium-ink">{{ recentOrder.order_number }}</p>
            <p class="mt-1 text-sm text-stadium-secondary">
                {{ formatPlacedAt(recentOrder.placed_at) }}
                · {{ recentOrder.status }}
                · {{ formatMoney(recentOrder.grand_total) }}
            </p>
            <Link
                :href="route('store.account.orders.show', recentOrder.order_number)"
                class="mt-4 inline-block text-sm font-semibold text-store-primary underline underline-offset-4"
            >
                View details
            </Link>
        </section>
        <p v-else class="mt-8 text-sm text-stadium-secondary">
            No orders yet.
            <Link :href="route('store.shop')" class="font-semibold text-store-primary underline">Start shopping</Link>
        </p>
    </StoreAccountLayout>
</template>
