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
                ? 'rounded-2xl bg-stadium-muted/90 px-4 py-3 text-xs text-stadium-secondary ring-1 ring-stadium-outline-soft/50'
                : 'grid grid-cols-3 gap-4 border-t border-stadium-outline-soft/30 pt-8 text-center md:gap-8'
        "
    >
        <template v-if="!compact">
            <div class="flex flex-col items-center gap-2">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-stadium-container-high text-stadium-ink"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 12h3l2 7 10 0 2-7h3" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M7 12V7a5 5 0 0110 0v5" stroke-linecap="round" />
                    </svg>
                </div>
                <span class="text-xs font-medium leading-snug text-stadium-ink-variant">
                    Fast nationwide delivery ({{ storefront.delivery_days_min }}–{{ storefront.delivery_days_max }} days)
                </span>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-stadium-container-high text-stadium-ink"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke-linejoin="round" />
                        <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <span class="text-xs font-medium leading-snug text-stadium-ink-variant">
                    100% authentic gear
                </span>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-stadium-container-high text-stadium-ink"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M21 15a4 4 0 01-4 4H7l-4 4V7a4 4 0 014-4h10a4 4 0 014 4v8z" stroke-linejoin="round" />
                        <path d="M9 10h.01M12 10h.01M15 10h.01" stroke-linecap="round" />
                    </svg>
                </div>
                <span class="text-xs font-medium leading-snug text-stadium-ink-variant">
                    Expert sizing & checkout help
                </span>
            </div>
        </template>
        <template v-else>
            <p>
                <span class="font-medium text-stadium-ink">Delivery</span>
                · {{ storefront.delivery_days_min }}–{{ storefront.delivery_days_max }} days
                · <span class="font-medium text-stadium-ink">Secure payment</span>
                · COD & wallets
            </p>
        </template>
    </div>
</template>
