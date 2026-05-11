<script setup>
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreSectionHeader from '@/Components/Store/StoreSectionHeader.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    featured: { type: Array, default: () => [] },
    bestSellers: { type: Array, default: () => [] },
    newArrivals: { type: Array, default: () => [] },
    trending: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    hero: { type: Object, required: true },
    seo: { type: Object, required: true },
});
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <!-- Hero -->
        <section class="relative overflow-hidden border-b border-stone-200/80 bg-stone-950">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_rgba(255,255,255,0.08),_transparent_55%)]" />
            <div class="relative mx-auto max-w-5xl px-4 pb-16 pt-14 sm:px-6 sm:pb-20 sm:pt-20">
                <p
                    v-if="hero.badge"
                    class="inline-flex rounded-full bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-widest text-emerald-300/95 ring-1 ring-white/15"
                >
                    {{ hero.badge }}
                </p>
                <h1 class="mt-5 max-w-3xl text-balance text-3xl font-semibold tracking-tight text-white sm:text-4xl md:text-5xl">
                    {{ hero.title }}
                </h1>
                <p class="mt-4 max-w-xl text-pretty text-base leading-relaxed text-stone-400 sm:text-lg">
                    {{ hero.subtitle }}
                </p>
                <div class="mt-10 flex flex-wrap gap-3">
                    <Link
                        :href="categories[0]?.slug ? route('store.category', categories[0].slug) : route('store.home')"
                        class="inline-flex min-h-12 items-center justify-center rounded-full bg-white px-7 text-sm font-semibold text-stone-900 shadow-lg transition active:scale-[0.98] sm:min-h-11"
                    >
                        Shop now
                    </Link>
                    <Link
                        href="#best-sellers"
                        class="inline-flex min-h-12 items-center justify-center rounded-full border border-white/25 px-7 text-sm font-semibold text-white transition hover:bg-white/10 active:scale-[0.98]"
                    >
                        View best sellers
                    </Link>
                </div>
            </div>
        </section>

        <!-- Categories -->
        <section v-if="categories.length" class="border-b border-stone-100 bg-white py-10">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <StoreSectionHeader
                    title="Shop by category"
                    subtitle="Tap a lane — filters help you narrow size, brand, and colour."
                />
                <div class="-mx-4 mt-6 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                    <template v-for="c in categories" :key="c.id">
                        <Link
                            v-if="c.slug"
                            :href="route('store.category', c.slug)"
                            class="flex min-h-11 shrink-0 items-center rounded-full bg-stone-50 px-5 py-2.5 text-sm font-semibold text-stone-800 ring-1 ring-stone-200/90 transition hover:bg-white hover:ring-stone-300 active:scale-[0.98]"
                        >
                            {{ c.name }}
                        </Link>
                    </template>
                    <template
                        v-for="ch in categories.flatMap((x) => x.children || []).filter((row) => row.slug).slice(0, 6)"
                        :key="'ch-' + ch.id"
                    >
                        <Link
                            :href="route('store.category', ch.slug)"
                            class="flex min-h-11 shrink-0 items-center rounded-full bg-white px-5 py-2.5 text-sm font-medium text-stone-600 ring-1 ring-stone-200/90 transition hover:ring-stone-300"
                        >
                            {{ ch.name }}
                        </Link>
                    </template>
                </div>
            </div>
        </section>

        <!-- Staff picks -->
        <section v-if="featured.length" class="bg-stone-50 py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <StoreSectionHeader title="Staff picks" subtitle="Curated pairs our team would wear every week." />
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in featured" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- Best sellers -->
        <section id="best-sellers" class="scroll-mt-20 bg-white py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <StoreSectionHeader
                    title="Best sellers"
                    subtitle="Most-loved silhouettes — yours might be one tap away."
                />
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in bestSellers" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- New arrivals -->
        <section class="bg-stone-50 py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <StoreSectionHeader title="New arrivals" subtitle="Fresh drops with updated sizing charts." />
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in newArrivals" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- Trending -->
        <section class="bg-white py-12 pb-20">
            <div class="mx-auto max-w-5xl px-4 sm:px-6">
                <StoreSectionHeader title="Trending now" subtitle="What shoppers are viewing right now." />
                <div class="mt-8 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in trending" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>
    </StoreLayout>
</template>
