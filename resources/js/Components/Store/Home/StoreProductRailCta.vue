<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    label: { type: String, default: 'View all cleats' },
    subtitle: { type: String, default: 'Browse every pair in stock' },
});
</script>

<template>
    <Link
        :href="href"
        class="group relative flex h-full min-h-full flex-col overflow-hidden rounded-3xl border-2 border-store-primary/70 bg-stadium-white stadium-ambient-shadow transition duration-200 hover:border-store-primary hover:shadow-stadium-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-store-primary active:scale-[0.98] dark:border-store-primary/60 dark:bg-stadium-white dark:hover:border-store-primary"
        :aria-label="label"
    >
        <div
            class="relative flex flex-1 flex-col items-center justify-center gap-4 bg-stadium-muted p-6 text-center dark:bg-stadium-container md:aspect-[4/5] md:min-h-0"
        >
            <div
                class="pointer-events-none absolute inset-0 store-pitch-pattern opacity-20 dark:opacity-30"
                aria-hidden="true"
            />
            <span
                class="relative z-[1] flex h-12 w-12 items-center justify-center rounded-full border-2 border-store-primary text-store-primary transition group-hover:scale-105 group-hover:bg-store-primary/10 sm:h-14 sm:w-14"
                aria-hidden="true"
            >
                <svg class="h-6 w-6 sm:h-7 sm:w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div class="relative z-[1]">
                <p class="font-display text-sm font-extrabold uppercase tracking-wide text-stadium-ink dark:text-white sm:text-base">
                    {{ label }}
                </p>
                <p
                    v-if="subtitle"
                    class="mt-2 text-xs font-medium text-stadium-secondary dark:text-stadium-inverse-text/75"
                >
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <div
            class="border-t border-stadium-outline-soft/30 bg-stadium-white px-3 py-3 text-center dark:border-stadium-outline-soft/20 dark:bg-stadium-white md:px-4 md:py-3.5"
        >
            <span class="font-display text-xs font-bold uppercase tracking-wider text-store-primary">
                Shop all →
            </span>
        </div>
    </Link>
</template>
