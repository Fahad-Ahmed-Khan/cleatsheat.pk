<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const storefront = computed(() => page.props.storefront ?? {});

const items = computed(() => [
    {
        title: 'Fast delivery',
        body: `Nationwide ${storefront.value.delivery_days_min}–${storefront.value.delivery_days_max} day shipping across Pakistan.`,
        icon: 'truck',
    },
    {
        title: 'Verified originals',
        body: 'Every pair is inspected, cleaned, and listed with honest condition notes.',
        icon: 'shield',
    },
    {
        title: 'COD & wallets',
        body: 'Cash on delivery, JazzCash, and Easypaisa — pay how you play.',
        icon: 'card',
    },
    {
        title: 'WhatsApp sizing',
        body: 'UK / EU fit help before you checkout — message us your usual boot.',
        icon: 'chat',
    },
]);
</script>

<template>
    <section class="store-section border-t border-stadium-outline-soft/30 bg-stadium-muted/50" aria-labelledby="trust-heading">
        <div class="store-container">
            <h2 id="trust-heading" class="text-display-md text-stadium-ink">Why CleatSheat.pk</h2>
            <p class="mt-2 max-w-xl text-base text-stadium-secondary">
                Premium used football boots — built for Pakistani pitches and budgets.
            </p>
            <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-6">
                <div
                    v-for="item in items"
                    :key="item.title"
                    class="rounded-2xl border border-stadium-outline-soft/40 bg-stadium-white p-5 stadium-ambient-shadow transition hover:-translate-y-0.5 hover:shadow-stadium-lg"
                >
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-stadium-lime/20 text-stadium-olive"
                    >
                        <svg
                            v-if="item.icon === 'truck'"
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M3 7h11v9H3z" />
                            <path d="M14 10h4l3 3v3h-7" />
                            <circle cx="7.5" cy="17.5" r="1.8" />
                            <circle cx="17.5" cy="17.5" r="1.8" />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'shield'"
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            <path d="M9 12l2 2 4-4" stroke-linecap="round" />
                        </svg>
                        <svg
                            v-else-if="item.icon === 'card'"
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <rect x="2" y="5" width="20" height="14" rx="2" />
                            <path d="M2 10h20" />
                        </svg>
                        <svg
                            v-else
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M21 15a4 4 0 01-4 4H7l-4 4V7a4 4 0 014-4h10a4 4 0 014 4v8z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 font-display text-sm font-bold text-stadium-ink">{{ item.title }}</h3>
                    <p class="mt-1 text-xs leading-relaxed text-stadium-secondary">{{ item.body }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
