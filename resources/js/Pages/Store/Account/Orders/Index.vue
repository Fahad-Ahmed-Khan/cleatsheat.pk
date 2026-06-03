<script setup>
import StoreAccountLayout from '@/Layouts/StoreAccountLayout.vue';
import StoreOrderBadges from '@/Components/Store/Account/StoreOrderBadges.vue';
import StoreWhatsAppButton from '@/Components/Store/Account/StoreWhatsAppButton.vue';
import { useStoreWhatsApp } from '@/composables/useStoreWhatsApp';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    seo: { type: Object, required: true },
    orders: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
});

const { orderSupportUrl } = useStoreWhatsApp();

const cardClass =
    'flex gap-4 rounded-2xl bg-stadium-white p-4 shadow-stadium ring-1 ring-stadium-outline-soft/50 transition hover:ring-store-primary/40 sm:p-5';

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
                <div :class="cardClass">
                    <Link
                        :href="route('store.account.orders.show', order.order_number)"
                        class="flex min-w-0 flex-1 gap-4"
                    >
                        <div
                            class="relative h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-stadium-muted ring-1 ring-stadium-outline-soft/80 sm:h-24 sm:w-24"
                        >
                            <img
                                v-if="order.thumbnail_url"
                                :src="order.thumbnail_url"
                                alt=""
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center px-1 text-center text-[10px] font-semibold uppercase leading-tight text-stadium-secondary"
                            >
                                No image
                            </div>
                            <span
                                v-if="order.item_count > 1"
                                class="absolute bottom-1 right-1 rounded-md bg-stadium-ink/80 px-1.5 py-0.5 text-[10px] font-bold text-white"
                            >
                                {{ order.item_count }} items
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="font-mono text-base font-bold text-stadium-ink">{{ order.order_number }}</p>
                                <p class="text-sm font-bold tabular-nums text-stadium-ink">{{ formatMoney(order.grand_total) }}</p>
                            </div>
                            <p v-if="order.items_preview" class="mt-1 line-clamp-2 text-sm text-stadium-secondary">
                                {{ order.items_preview }}
                            </p>
                            <p class="mt-1 text-xs text-stadium-secondary">{{ formatPlacedAt(order.placed_at) }}</p>
                            <div class="mt-2">
                                <StoreOrderBadges :status="order.status" :payment-status="order.payment_status" />
                            </div>
                        </div>
                    </Link>
                    <div class="flex shrink-0 flex-col items-end justify-between gap-2 py-0.5">
                        <StoreWhatsAppButton
                            :href="orderSupportUrl(order)"
                            label="Help"
                            compact
                        />
                        <Link
                            :href="route('store.account.orders.show', order.order_number)"
                            class="text-xs font-semibold text-store-primary underline decoration-store-primary/30 underline-offset-2"
                        >
                            View
                        </Link>
                    </div>
                </div>
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
