<script setup>
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { useStoreAnalytics } from '@/composables/useStoreAnalytics';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
    notice: {
        type: String,
        default: null,
    },
    order: {
        type: Object,
        required: true,
    },
    seo: {
        type: Object,
        required: true,
    },
});

const analytics = useStoreAnalytics();

onMounted(() => {
    analytics.trackPurchase(props.order);
});

function formatPrice(n) {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        maximumFractionDigits: 0,
    }).format(n);
}
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <div class="store-container py-12 md:py-16">
            <div
                class="mx-auto max-w-lg rounded-2xl border border-stadium-outline-soft/40 bg-stadium-white px-6 py-10 text-center shadow-stadium dark:border-white/10 dark:bg-stadium-container md:px-8 md:py-12"
            >
                <p class="text-label text-emerald-700 dark:text-emerald-400">
                    Order placed
                </p>
                <h1 class="mt-2 text-display-md text-stadium-ink">
                    Thank you
                </h1>
                <p
                    v-if="notice"
                    class="mx-auto mt-4 max-w-md rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-100"
                >
                    {{ notice }}
                </p>
                <p class="mt-4 text-sm text-stadium-secondary">
                    Reference
                    <span class="font-mono font-semibold text-stadium-ink">{{ order.order_number }}</span>
                </p>
                <p class="mt-2 text-lg font-semibold text-stadium-ink">
                    {{ formatPrice(order.grand_total) }}
                </p>
                <p class="mt-6 text-xs font-bold uppercase tracking-wide text-stadium-secondary">
                    Payment: {{ order.payment_gateway }} · {{ order.payment_status }}
                </p>

                <ul class="mt-10 space-y-3 border-t border-stadium-outline-soft/30 pt-8 text-left text-sm dark:border-white/10">
                    <li
                        v-for="(item, idx) in order.items"
                        :key="idx"
                        class="flex justify-between gap-4 border-b border-stadium-outline-soft/25 pb-3 last:border-0 dark:border-white/10"
                    >
                        <span class="text-stadium-secondary">
                            {{ item.product_name }} · {{ item.variant_label }} · {{ item.size_label }}
                            × {{ item.quantity }}
                        </span>
                        <span class="shrink-0 font-semibold text-stadium-ink">{{ formatPrice(item.line_total) }}</span>
                    </li>
                </ul>

                <Link
                    :href="route('store.home')"
                    class="mt-10 inline-flex min-h-11 items-center justify-center rounded-2xl bg-store-primary px-8 py-3 text-label text-store-primary-fg shadow-md transition hover:opacity-95"
                >
                    Continue shopping
                </Link>
            </div>
        </div>
    </StoreLayout>
</template>
