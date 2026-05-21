<script setup>
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    seo: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
});

const cardClass =
    'block rounded-2xl bg-stadium-white p-5 shadow-stadium ring-1 ring-stadium-outline-soft/50 transition hover:ring-store-primary/40';

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

function goToPage(page) {
    router.get(route('store.account.orders.index'), { page }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <StoreAccountLayout :seo="seo" title="Order history">
        <p v-if="!orders.length" class="text-sm text-stadium-secondary">
            You have not placed any orders yet.
            <Link :href="route('store.shop')" class="font-semibold text-store-primary underline">Browse the shop</Link>
        </p>
        <ul v-else class="space-y-3">
            <li v-for="order in orders" :key="order.order_number">
                <Link
                    :href="route('store.account.orders.show', order.order_number)"
                    :class="cardClass"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-base font-bold text-stadium-ink">{{ order.order_number }}</p>
                            <p class="mt-1 text-xs text-stadium-secondary">
                                {{ formatPlacedAt(order.placed_at) }}
                                · {{ order.status }}
                                · Payment {{ order.payment_status }}
                            </p>
                        </div>
                        <p class="text-sm font-bold tabular-nums text-stadium-ink">{{ formatMoney(order.grand_total) }}</p>
                    </div>
                </Link>
            </li>
        </ul>
        <nav
            v-if="pagination.last_page > 1"
            class="mt-8 flex items-center justify-center gap-4 text-sm"
        >
            <button
                type="button"
                class="font-semibold text-stadium-ink disabled:opacity-40"
                :disabled="pagination.current_page <= 1"
                @click="goToPage(pagination.current_page - 1)"
            >
                Previous
            </button>
            <span class="text-stadium-secondary">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button
                type="button"
                class="font-semibold text-stadium-ink disabled:opacity-40"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="goToPage(pagination.current_page + 1)"
            >
                Next
            </button>
        </nav>
    </StoreAccountLayout>
</template>
