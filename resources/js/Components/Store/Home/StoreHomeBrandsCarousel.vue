<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    brands: { type: Array, default: () => [] },
});

const items = computed(() =>
    (props.brands ?? []).filter((b) => b?.slug && (b.logo_url || b.name)),
);

function brandHref(brand) {
    return route('store.shop', { brand_ids: [brand.id] });
}

function brandInitial(name) {
    return String(name ?? '?').trim().charAt(0).toUpperCase();
}
</script>

<template>
    <section
        v-if="items.length"
        class="store-section border-t border-stadium-outline-soft/20 bg-stadium-white py-10 md:py-12"
        aria-labelledby="brands-heading"
    >
        <div class="store-container">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-label text-stadium-olive">Official partners</p>
                    <h2 id="brands-heading" class="text-display-md text-stadium-ink">Shop by brand</h2>
                    <p class="mt-1 text-sm text-stadium-secondary md:text-base">
                        Nike, Adidas, Puma &amp; more — tap a logo to filter the shop.
                    </p>
                </div>
                <Link
                    :href="route('store.shop')"
                    class="mt-2 inline-flex items-center gap-1 font-display text-xs font-bold uppercase tracking-wide text-stadium-olive hover:underline sm:mt-0"
                >
                    All brands
                    <span aria-hidden="true">→</span>
                </Link>
            </div>

            <div
                class="no-scrollbar relative mt-6 flex gap-3 overflow-x-auto pb-2 snap-x sm:mt-8 sm:gap-4"
            >
                <Link
                    v-for="brand in items"
                    :key="brand.id"
                    data-brand-card
                    :href="brandHref(brand)"
                    class="group flex w-[7.5rem] shrink-0 snap-start flex-col items-center gap-2 rounded-2xl border border-stadium-outline-soft/50 bg-stadium-muted/80 px-3 py-4 transition hover:-translate-y-0.5 hover:border-store-primary/40 hover:bg-stadium-white hover:shadow-stadium sm:w-[8.5rem]"
                    :aria-label="`Shop ${brand.name}`"
                >
                    <div
                        class="flex h-14 w-full items-center justify-center rounded-xl bg-stadium-white p-2 ring-1 ring-stadium-outline-soft/40 transition group-hover:ring-store-primary/30 sm:h-16"
                    >
                        <img
                            v-if="brand.logo_url"
                            :src="brand.logo_url"
                            :alt="`${brand.name} logo`"
                            class="max-h-full max-w-full object-contain"
                            loading="lazy"
                            decoding="async"
                        >
                        <span
                            v-else
                            class="font-display text-xl font-bold text-stadium-olive"
                            aria-hidden="true"
                        >
                            {{ brandInitial(brand.name) }}
                        </span>
                    </div>
                    <span class="line-clamp-1 text-center text-[11px] font-semibold text-stadium-ink sm:text-xs">
                        {{ brand.name }}
                    </span>
                </Link>
            </div>
        </div>
    </section>
</template>
