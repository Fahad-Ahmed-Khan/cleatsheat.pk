<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const storefront = computed(() => page.props.storefront ?? {});

defineProps({
    /** compact = single row for cart/checkout */
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div
        :class="
            compact
                ? 'rounded-2xl bg-stone-100/80 px-4 py-3 text-xs text-stone-600 ring-1 ring-stone-200/80'
                : 'grid gap-3 sm:grid-cols-3'
        "
    >
        <template v-if="!compact">
            <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-stone-200/70 transition hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                    Delivery
                </p>
                <p class="mt-2 text-sm font-medium text-stone-900">
                    {{ storefront.delivery_days_min }}–{{ storefront.delivery_days_max }} days
                </p>
                <p class="mt-1 text-xs text-stone-500">
                    Nationwide
                </p>
            </div>
            <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-stone-200/70 transition hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                    Returns
                </p>
                <p class="mt-2 text-sm leading-snug text-stone-700">
                    {{ storefront.return_policy_summary }}
                </p>
            </div>
            <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-stone-200/70 transition hover:shadow-md">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                    Checkout
                </p>
                <p class="mt-2 text-sm font-medium text-stone-900">
                    COD & wallets
                </p>
                <p class="mt-1 text-xs text-stone-500">
                    Encrypted session · Pak logistics
                </p>
            </div>
        </template>
        <template v-else>
            <p>
                <span class="font-medium text-stone-800">Delivery</span>
                · {{ storefront.delivery_days_min }}–{{ storefront.delivery_days_max }} days
                · <span class="font-medium text-stone-800">Secure payment</span>
                · COD & wallets
            </p>
        </template>
    </div>
</template>
