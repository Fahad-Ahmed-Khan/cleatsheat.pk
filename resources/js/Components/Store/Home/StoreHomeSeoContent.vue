<script setup>
import { SURFACE_TILES, useStoreCategoryHref } from '@/composables/useStoreCategoryHref';
import { Link } from '@inertiajs/vue3';
import { toRef } from 'vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
    seoHtml: { type: String, default: null },
});

const { categoryHref } = useStoreCategoryHref(toRef(props, 'categories'));
</script>

<template>
    <section
        class="store-section bg-stadium-inverse text-stadium-inverse-text store-pitch-pattern"
        aria-labelledby="seo-content-heading"
    >
        <div class="store-container">
            <h2 id="seo-content-heading" class="text-display-md text-white">
                Buy football boots in Pakistan
            </h2>

            <div
                v-if="seoHtml"
                class="prose prose-invert mt-6 max-w-none text-sm leading-relaxed text-stadium-inverse-text/85"
                v-html="seoHtml"
            />

            <div v-else class="mt-6 space-y-6 text-sm leading-relaxed text-stadium-inverse-text/85">
                <p>
                    <strong class="text-white">CleatSheat.pk</strong> specialises in
                    <strong class="text-stadium-lime">original used football boots</strong> for players in
                    Lahore, Karachi, Islamabad, and nationwide. Every listing includes UK/EU size clarity,
                    surface-matched plates, and honest condition grading before dispatch.
                </p>

                <div>
                    <h3 class="font-display text-lg font-bold text-white">Firm ground (FG) boots Pakistan</h3>
                    <p class="mt-2">
                        FG cleats suit natural dry grass — the most common Sunday league and academy surface.
                        <Link
                            :href="categoryHref(SURFACE_TILES[0].fragments)"
                            class="font-semibold text-stadium-lime hover:underline"
                        >
                            Shop FG boots
                        </Link>
                        for speed-focused silos and classic leather touch.
                    </p>
                </div>

                <div>
                    <h3 class="font-display text-lg font-bold text-white">Soft ground (SG) for wet pitches</h3>
                    <p class="mt-2">
                        Metal or longer studs grip muddy Karachi and monsoon-season grounds.
                        <Link
                            :href="categoryHref(SURFACE_TILES[1].fragments)"
                            class="font-semibold text-stadium-lime hover:underline"
                        >
                            Browse SG cleats
                        </Link>
                        when the pitch turns heavy.
                    </p>
                </div>

                <div>
                    <h3 class="font-display text-lg font-bold text-white">AG & Turf football shoes</h3>
                    <p class="mt-2">
                        Artificial 3G/4G turf and indoor courts need shorter studs — wrong plates increase injury risk.
                        Explore
                        <Link
                            :href="categoryHref(SURFACE_TILES[2].fragments)"
                            class="font-semibold text-stadium-lime hover:underline"
                        >
                            AG boots
                        </Link>
                        and
                        <Link
                            :href="categoryHref(SURFACE_TILES[3].fragments)"
                            class="font-semibold text-stadium-lime hover:underline"
                        >
                            Turf / indoor trainers
                        </Link>.
                    </p>
                </div>

                <div>
                    <h3 class="font-display text-lg font-bold text-white">UK & EU sizing for Pakistani players</h3>
                    <p class="mt-2">
                        We list UK and EU sizes on every card. Unsure? Message us on WhatsApp before checkout or read our
                        <Link :href="route('store.journal.index')" class="font-semibold text-stadium-lime hover:underline">
                            sizing guides
                        </Link>.
                    </p>
                </div>
            </div>

            <nav class="mt-8 flex flex-wrap gap-3" aria-label="Related shop links">
                <Link
                    :href="route('store.shop')"
                    class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:border-stadium-lime"
                >
                    All boots
                </Link>
                <Link
                    v-for="tile in SURFACE_TILES"
                    :key="tile.short"
                    :href="categoryHref(tile.fragments)"
                    class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white hover:border-stadium-lime"
                >
                    {{ tile.short }} boots
                </Link>
            </nav>
        </div>
    </section>
</template>
