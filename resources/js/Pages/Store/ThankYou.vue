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
        <div class="mx-auto max-w-lg px-4 py-16 text-center">
            <p class="text-sm font-medium text-emerald-700">
                Order placed
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-stone-900">
                Thank you
            </h1>
            <p
                v-if="notice"
                class="mx-auto mt-4 max-w-md rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                {{ notice }}
            </p>
            <p class="mt-4 text-sm text-stone-600">
                Reference <span class="font-mono font-semibold text-stone-900">{{ order.order_number }}</span>
            </p>
            <p class="mt-2 text-lg font-semibold text-stone-900">
                {{ formatPrice(order.grand_total) }}
            </p>
            <p class="mt-6 text-xs uppercase text-stone-500">
                Payment: {{ order.payment_gateway }} · {{ order.payment_status }}
            </p>

            <ul class="mt-10 space-y-3 text-left text-sm">
                <li
                    v-for="(item, idx) in order.items"
                    :key="idx"
                    class="flex justify-between gap-4 border-b border-stone-100 pb-3"
                >
                    <span class="text-stone-700">
                        {{ item.product_name }} · {{ item.variant_label }} · {{ item.size_label }}
                        × {{ item.quantity }}
                    </span>
                    <span class="shrink-0 font-medium">{{ formatPrice(item.line_total) }}</span>
                </li>
            </ul>

            <Link
                :href="route('store.home')"
                class="mt-10 inline-block rounded-full bg-stone-900 px-8 py-3 text-sm font-semibold text-white"
            >
                Continue shopping
            </Link>
        </div>
    </StoreLayout>
</template>
