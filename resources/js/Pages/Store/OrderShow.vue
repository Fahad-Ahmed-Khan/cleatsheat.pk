<script setup>
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    order: {
        type: Object,
        required: true,
    },
    seo: {
        type: Object,
        required: true,
    },
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
    <Head>
        <title>{{ seo.title }}</title>
    </Head>
    <StoreLayout>
        <div class="mx-auto max-w-3xl px-4 py-10">
            <h1 class="text-2xl font-semibold text-stone-900">
                Order {{ order.order_number }}
            </h1>
            <p class="mt-2 text-sm text-stone-600">
                Status {{ order.status }} · Payment {{ order.payment_status }}
            </p>
            <p class="mt-4 text-lg font-semibold">
                {{ formatPrice(order.grand_total) }}
            </p>

            <section class="mt-10 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <h2 class="text-sm font-semibold uppercase text-stone-500">
                    Ship to
                </h2>
                <p class="mt-2 text-sm text-stone-800">
                    {{ order.shipping_address_snapshot.full_name }}<br>
                    {{ order.shipping_address_snapshot.phone }}<br>
                    {{ order.shipping_address_snapshot.line1 }}<br>
                    {{ order.shipping_address_snapshot.city }}
                    <template v-if="order.shipping_address_snapshot.area">
                        , {{ order.shipping_address_snapshot.area }}
                    </template>
                </p>
            </section>

            <section v-if="order.shipments?.length" class="mt-6 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
                <h2 class="text-sm font-semibold uppercase text-stone-500">
                    Delivery
                </h2>
                <div v-for="(s, i) in order.shipments" :key="i" class="mt-4 border-t border-stone-100 pt-4 first:mt-2 first:border-t-0 first:pt-0">
                    <p class="text-sm font-medium text-stone-900">
                        {{ s.courier ?? 'Courier' }}
                        <span class="ml-2 rounded-full bg-stone-100 px-2 py-0.5 text-xs font-normal text-stone-700">{{ s.status }}</span>
                    </p>
                    <p class="mt-1 font-mono text-sm text-stone-800">
                        {{ s.tracking_number ?? 'Awaiting booking' }}
                    </p>
                    <a
                        v-if="s.label_url"
                        :href="s.label_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-2 inline-block text-sm font-medium text-stone-900 underline"
                    >
                        Shipping label
                    </a>
                    <ul v-if="s.events?.length" class="mt-4 space-y-3 border-l-2 border-stone-200 pl-4">
                        <li v-for="(e, j) in s.events" :key="j" class="text-sm">
                            <span class="text-stone-900">{{ e.description ?? e.status }}</span>
                            <span v-if="e.occurred_at" class="mt-0.5 block text-xs text-stone-500">{{ new Date(e.occurred_at).toLocaleString() }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <ul class="mt-8 divide-y divide-stone-100">
                <li
                    v-for="item in order.items"
                    :key="item.id"
                    class="flex justify-between gap-4 py-4 text-sm"
                >
                    <span>{{ item.product_name }} · {{ item.variant_label }} · {{ item.size_label }} × {{ item.quantity }}</span>
                    <span class="font-medium">{{ formatPrice(item.line_total) }}</span>
                </li>
            </ul>
        </div>
    </StoreLayout>
</template>
