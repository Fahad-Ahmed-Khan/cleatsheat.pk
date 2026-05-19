<script setup>
import StoreProductCard from '@/Components/Store/StoreProductCard.vue';
import StoreSectionHeader from '@/Components/Store/StoreSectionHeader.vue';
import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';
import StoreTrustStrip from '@/Components/Store/StoreTrustStrip.vue';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    featured: { type: Array, default: () => [] },
    bestSellers: { type: Array, default: () => [] },
    newArrivals: { type: Array, default: () => [] },
    trending: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    hero: { type: Object, required: true },
    seo: { type: Object, required: true },
});

const flatCategories = computed(() => {
    const roots = props.categories || [];
    return [...roots, ...roots.flatMap((x) => x.children || [])];
});

function categoryHref(fragments) {
    const fr = fragments.map((f) => f.toLowerCase());
    for (const cat of flatCategories.value) {
        if (!cat?.slug) {
            continue;
        }
        const slug = String(cat.slug).toLowerCase();
        const name = String(cat.name || '').toLowerCase();
        if (fr.some((f) => slug.includes(f) || name.includes(f))) {
            return route('store.category', cat.slug);
        }
    }
    return route('store.shop');
}

const heroStyle = computed(() => {
    const url = props.hero?.image_url;
    if (!url) {
        return {};
    }
    return {
        backgroundImage: `linear-gradient(to top, rgba(26,28,28,0.82), rgba(26,28,28,0.35), transparent), url("${url}")`,
        backgroundSize: 'cover',
        backgroundPosition: 'center',
    };
});

const surfaceTiles = [
    {
        title: 'Firm Ground',
        subtitle: 'Natural dry grass',
        fragments: ['fg', 'firm'],
    },
    {
        title: 'Soft Ground',
        subtitle: 'Wet, muddy grass',
        fragments: ['sg', 'soft'],
    },
    {
        title: 'Artificial Grass',
        subtitle: 'Modern 3G / 4G turf',
        fragments: ['ag', 'artificial'],
    },
    {
        title: 'Turf / Indoor',
        subtitle: 'Astro and courts',
        fragments: ['turf', 'indoor', 'astro', 'court', 'tf'],
    },
];

const primaryShopHref = computed(() => categoryHref(['fg', 'firm']));
</script>

<template>
    <StoreSeoHead :seo="seo" />
    <StoreLayout>
        <!-- Desktop hero -->
        <section class="relative hidden min-h-[600px] w-full overflow-hidden md:block md:min-h-[700px]">
            <div
                class="absolute inset-0 bg-stadium-container-high"
                :style="hero.image_url ? heroStyle : {}"
            >
                <div
                    v-if="!hero.image_url"
                    class="absolute inset-0 bg-[linear-gradient(135deg,#1a1c1c_0%,#2f3131_40%,#576500_100%)]"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-stadium-ink/85 via-stadium-ink/45 to-transparent"
                />
            </div>
            <div
                class="relative z-10 mx-auto flex max-w-content min-h-[600px] flex-col items-center justify-center px-6 py-20 text-center md:min-h-[700px]"
            >
                <span
                    v-if="hero.badge"
                    class="mb-6 inline-block rounded-full bg-stadium-lime px-4 py-1.5 font-display text-xs font-bold uppercase tracking-widest text-stadium-ink shadow-md"
                >
                    {{ hero.badge }}
                </span>
                <h1
                    class="max-w-4xl font-display text-4xl font-extrabold leading-[1.1] tracking-tighter text-white drop-shadow-md lg:text-6xl xl:text-[64px]"
                >
                    {{ hero.title }}
                </h1>
                <p class="mt-4 max-w-2xl text-lg leading-relaxed text-white/90 drop-shadow-md">
                    {{ hero.subtitle }}
                </p>
                <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                    <Link
                        :href="primaryShopHref"
                        class="inline-flex items-center gap-2 rounded-2xl bg-stadium-lime px-8 py-4 font-display text-sm font-bold uppercase tracking-wide text-stadium-ink shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl active:scale-[0.98]"
                    >
                        Shop FG boots
                        <span aria-hidden="true">→</span>
                    </Link>
                    <Link
                        href="#best-sellers"
                        class="inline-flex items-center justify-center rounded-2xl border-2 border-white/40 px-8 py-4 font-display text-sm font-bold uppercase tracking-wide text-white transition hover:bg-white/10"
                    >
                        Best sellers
                    </Link>
                </div>
            </div>
        </section>

        <!-- Mobile hero -->
        <section class="px-4 pb-6 pt-6 md:hidden">
            <div class="relative min-h-[480px] overflow-hidden rounded-2xl shadow-stadium-lg">
                <div
                    class="absolute inset-0 bg-stadium-container-high bg-cover bg-center"
                    :style="hero.image_url ? heroStyle : {}"
                />
                <div
                    v-if="!hero.image_url"
                    class="absolute inset-0 bg-[linear-gradient(145deg,#1a1c1c_0%,#414c00_50%,#576500_100%)]"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-transparent"
                />
                <div class="relative z-10 flex min-h-[480px] flex-col justify-end p-6 pb-10">
                    <span
                        v-if="hero.badge"
                        class="mb-4 inline-flex w-fit rounded-md bg-stadium-lime px-3 py-1 font-display text-xs font-bold uppercase tracking-widest text-stadium-lime-ink shadow-sm"
                    >
                        {{ hero.badge }}
                    </span>
                    <h1
                        class="max-w-[14ch] font-display text-[40px] font-extrabold uppercase leading-tight tracking-tighter text-white drop-shadow-md"
                    >
                        {{ hero.title }}
                    </h1>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-white/85">
                        {{ hero.subtitle }}
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="primaryShopHref"
                            class="inline-flex items-center gap-2 rounded-xl bg-stadium-lime px-8 py-4 font-display text-xs font-bold uppercase tracking-wide text-stadium-lime-ink shadow-md transition hover:-translate-y-0.5 active:scale-[0.98]"
                        >
                            Shop FG boots
                            <span aria-hidden="true">→</span>
                        </Link>
                        <Link
                            href="#best-sellers"
                            class="inline-flex items-center justify-center rounded-xl border border-white/30 px-6 py-4 text-sm font-semibold text-white"
                        >
                            Best sellers
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pitch surface — mobile scroll -->
        <section class="border-b border-stadium-outline-soft/30 md:hidden">
            <div class="px-4 pb-2 pt-2">
                <h2 class="font-display text-2xl font-bold tracking-tight text-stadium-ink">
                    Pitch surface
                </h2>
            </div>
            <div class="no-scrollbar flex gap-3 overflow-x-auto px-4 pb-6 pt-2 snap-x">
                <Link
                    v-for="tile in surfaceTiles"
                    :key="tile.title"
                    :href="categoryHref(tile.fragments)"
                    class="snap-start shrink-0 rounded-xl border border-stadium-outline-soft/40 bg-stadium-container-high px-5 py-3 font-display text-xs font-bold uppercase tracking-wide text-stadium-ink shadow-sm transition hover:bg-stadium-container"
                >
                    {{ tile.title }}
                </Link>
            </div>
        </section>

        <!-- Pitch surface — desktop grid -->
        <section class="hidden border-b border-stadium-outline-soft/20 py-16 md:block md:py-20">
            <div class="mx-auto max-w-content px-6">
                <div class="mb-10 flex items-center justify-between">
                    <h2 class="font-display text-3xl font-bold tracking-tight text-stadium-ink lg:text-[32px]">
                        Choose your surface
                    </h2>
                </div>
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 lg:gap-4">
                    <Link
                        v-for="tile in surfaceTiles"
                        :key="'d-' + tile.title"
                        :href="categoryHref(tile.fragments)"
                        class="group stadium-ambient-shadow relative aspect-[4/5] overflow-hidden rounded-[24px] border border-transparent bg-stadium-white transition hover:-translate-y-1 hover:border-stadium-outline-soft lg:aspect-square"
                    >
                        <div
                            class="absolute inset-0 z-10 flex flex-col justify-between bg-stadium-muted p-6"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-stadium-ink text-stadium-lime"
                            >
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path
                                        d="M14 6l-3.75 5 2.85 3.8-1.6 1.2L8.63 12 4 6h10zm-10 0l4.5 6 4.5-6H4zm16 0h-4.5l-4.5 6 3.35 4.47 1.6-1.2L14.38 12 20 6z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-display text-2xl font-bold tracking-tight text-stadium-ink">
                                    {{ tile.title }}
                                </h3>
                                <p
                                    class="mt-1 text-base leading-relaxed text-stadium-ink-variant transition group-hover:text-stadium-olive"
                                >
                                    {{ tile.subtitle }}
                                </p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Shop by category -->
        <section v-if="categories.length" class="border-b border-stadium-outline-soft/20 bg-stadium-white py-10">
            <div class="mx-auto max-w-content px-4 sm:px-6">
                <StoreSectionHeader
                    title="Shop by category"
                    subtitle="Tap a lane — filters help you narrow size, brand, and colour."
                />
                <div class="-mx-4 mt-6 flex gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                    <template v-for="c in categories" :key="c.id">
                        <Link
                            v-if="c.slug"
                            :href="route('store.category', c.slug)"
                            class="flex min-h-11 shrink-0 items-center rounded-xl border border-stadium-outline-soft/50 bg-stadium-muted px-5 py-2.5 text-sm font-semibold text-stadium-ink shadow-sm transition hover:border-stadium-outline hover:bg-stadium-white active:scale-[0.98]"
                        >
                            {{ c.name }}
                        </Link>
                    </template>
                    <template
                        v-for="ch in categories.flatMap((x) => x.children || []).filter((row) => row.slug).slice(0, 8)"
                        :key="'ch-' + ch.id"
                    >
                        <Link
                            :href="route('store.category', ch.slug)"
                            class="flex min-h-11 shrink-0 items-center rounded-xl border border-stadium-outline-soft/40 bg-stadium-white px-5 py-2.5 text-sm font-medium text-stadium-secondary transition hover:border-stadium-outline"
                        >
                            {{ ch.name }}
                        </Link>
                    </template>
                </div>
            </div>
        </section>

        <!-- Staff picks -->
        <section v-if="featured.length" class="bg-stadium py-14">
            <div class="mx-auto max-w-content px-4 sm:px-6">
                <StoreSectionHeader title="Staff picks" subtitle="Curated pairs our team would wear every week." />
                <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in featured" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- Best sellers -->
        <section id="best-sellers" class="scroll-mt-24 border-t border-stadium-outline-soft/20 bg-stadium-white py-14">
            <div class="mx-auto max-w-content px-4 sm:px-6">
                <StoreSectionHeader
                    title="Best sellers"
                    subtitle="Most-loved silhouettes — yours might be one tap away."
                />
                <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in bestSellers" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- New arrivals -->
        <section class="bg-stadium-container-low py-14">
            <div class="mx-auto max-w-content px-4 sm:px-6">
                <StoreSectionHeader title="New arrivals" subtitle="Fresh drops with updated sizing charts." />
                <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in newArrivals" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- Trending -->
        <section class="border-t border-stadium-outline-soft/20 bg-stadium-white py-14 pb-24 sm:pb-14">
            <div class="mx-auto max-w-content px-4 sm:px-6">
                <StoreSectionHeader title="Trending on pitch" subtitle="What shoppers are viewing right now.">
                    <template #action>
                        <Link
                            :href="route('store.shop')"
                            class="inline-flex items-center gap-1 font-display text-sm font-bold uppercase tracking-wide text-stadium-olive hover:underline"
                        >
                            View all
                            <span aria-hidden="true">→</span>
                        </Link>
                    </template>
                </StoreSectionHeader>
                <div class="mt-8 grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-4">
                    <StoreProductCard v-for="(p, i) in trending" :key="p.id" :product="p" :index="i" />
                </div>
            </div>
        </section>

        <!-- Editorial -->
        <section class="px-4 pb-14 pt-2 sm:px-6">
            <div class="mx-auto max-w-content">
                <div
                    class="relative overflow-hidden rounded-2xl bg-stadium-ink shadow-stadium-lg"
                >
                    <div
                        class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(223,255,0,0.12),_transparent_50%)]"
                    />
                    <div class="relative flex flex-col gap-6 p-8 md:w-2/3 md:p-12">
                        <span
                            class="inline-flex items-center gap-2 font-display text-xs font-bold uppercase tracking-widest text-stadium-lime"
                        >
                            Expert guide
                        </span>
                        <h3 class="font-display text-3xl font-extrabold leading-tight tracking-tighter text-white md:text-4xl">
                            How to choose your boots
                        </h3>
                        <p class="max-w-md text-base leading-relaxed text-stadium-inverse-text/90">
                            Match your play style and pitch surface to the right plate and upper — we break it down in the journal.
                        </p>
                        <Link
                            :href="route('store.journal.index')"
                            class="inline-flex w-fit items-center rounded-xl border-2 border-stadium-lime px-6 py-3 font-display text-sm font-bold uppercase tracking-wide text-stadium-lime transition hover:bg-stadium-lime hover:text-stadium-ink"
                        >
                            Read guide
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trust -->
        <section class="border-t border-stadium-outline-soft/30 bg-stadium px-4 py-10 sm:px-6">
            <div class="mx-auto max-w-content">
                <StoreTrustStrip />
            </div>
        </section>
    </StoreLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
