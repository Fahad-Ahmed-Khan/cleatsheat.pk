<script setup>

// Above-the-fold: eager so the hero/LCP + first rail render immediately.
import StoreHomeHero from '@/Components/Store/Home/StoreHomeHero.vue';

import StoreHomeCategoryBrowser from '@/Components/Store/Home/StoreHomeCategoryBrowser.vue';

import StoreProductRail from '@/Components/Store/Home/StoreProductRail.vue';

import StoreSeoHead from '@/Components/Store/StoreSeoHead.vue';

import StoreLazySection from '@/Components/Store/StoreLazySection.vue';

import StoreLayout from '@/Layouts/StoreLayout.vue';

import { useStoreQuickAdd } from '@/composables/useStoreQuickAdd';

import { Link } from '@inertiajs/vue3';

import { computed, defineAsyncComponent, onUnmounted, provide } from 'vue';

// Below-the-fold: split into their own chunks so the initial Home bundle stays small.
const StoreHomeTrust = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeTrust.vue'));
const StoreHomeTestimonials = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeTestimonials.vue'));
const StoreHomeBuyingGuides = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeBuyingGuides.vue'));
const StoreHomeBrandsCarousel = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeBrandsCarousel.vue'));
const StoreHomeSocialProof = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeSocialProof.vue'));
const StoreHomeNewsletter = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeNewsletter.vue'));
const StoreHomeSeoContent = defineAsyncComponent(() => import('@/Components/Store/Home/StoreHomeSeoContent.vue'));

const StoreProductQuickAddSheet = defineAsyncComponent(
    () => import('@/Components/Store/StoreProductQuickAddSheet.vue'),
);

const props = defineProps({

    featured: { type: Array, default: () => [] },

    bestSellers: { type: Array, default: () => [] },

    newArrivals: { type: Array, default: () => [] },

    trending: { type: Array, default: () => [] },

    categories: { type: Array, default: () => [] },

    hero: { type: Object, required: true },

    promoBanner: { type: Object, default: () => ({}) },

    homeContent: { type: Object, default: () => ({}) },

    journalPosts: { type: Array, default: () => [] },

    seo: { type: Object, required: true },

    shopProductCount: { type: Number, default: 0 },

    brands: { type: Array, default: () => [] },

});

const viewAllCleatsLabel = computed(() => {
    const n = Number(props.shopProductCount ?? 0);
    if (n >= 50) return `View all ${n}+ cleats`;
    if (n > 0) return `View all ${n} cleats`;
    return 'View all cleats';
});



const {
    sheetOpen,
    sheetProduct,
    adding,
    quickAdd,
    addWithSize,
    closeSheet,
} = useStoreQuickAdd();

provide('storeQuickAdd', { quickAdd });

onUnmounted(closeSheet);

function onSheetSelect({ variantId, sizeLabel }) {
    if (!sheetProduct.value) return;
    addWithSize(sheetProduct.value, variantId, sizeLabel);
}

</script>



<template>

    <StoreSeoHead :seo="seo" />

    <StoreLayout>

        <StoreHomeHero
            :hero="hero"
            :promo-banner="promoBanner"
            :categories="categories"
        />

        <StoreHomeCategoryBrowser :categories="categories" />



        <StoreProductRail

            v-if="featured.length"

            title="Staff picks"

            subtitle="Curated pairs our team would wear every week."

            :products="featured"

            bg-class="bg-stadium"

            scroll-mobile

            :view-all-label="viewAllCleatsLabel"

        />



        <StoreProductRail

            id="best-sellers"

            title="Best sellers"

            subtitle="Most-loved silhouettes — yours might be one tap away."

            :products="bestSellers"

            bg-class="bg-stadium-white"

            :view-all-label="viewAllCleatsLabel"

        />



        <StoreProductRail

            title="New arrivals"

            subtitle="Fresh drops with updated sizing charts."

            :products="newArrivals"

            bg-class="bg-stadium-container-low"

            :view-all-label="viewAllCleatsLabel"

        />



        <StoreProductRail

            id="trending"

            title="Trending on pitch"

            subtitle="What shoppers are viewing right now."

            :products="trending"

            bg-class="bg-stadium-white"

            :view-all-label="viewAllCleatsLabel"

        >

            <template #action>

                <Link

                    :href="route('store.shop')"

                    class="inline-flex items-center gap-1 font-display text-sm font-bold uppercase tracking-wide text-stadium-olive hover:underline"

                >

                    View all

                    <span aria-hidden="true">→</span>

                </Link>

            </template>

        </StoreProductRail>



        <StoreLazySection min-height="260px">
            <StoreHomeTrust />
        </StoreLazySection>



        <StoreLazySection min-height="420px">
            <StoreHomeTestimonials :testimonials="homeContent.testimonials ?? []" />
        </StoreLazySection>



        <!-- Journal links carry internal-linking SEO value: split chunk but render eagerly. -->
        <StoreHomeBuyingGuides :journal-posts="journalPosts" />



        <StoreLazySection min-height="220px">
            <StoreHomeBrandsCarousel :brands="brands" />
        </StoreLazySection>



        <StoreLazySection min-height="420px">
            <StoreHomeSocialProof :social="homeContent.social ?? {}" />
        </StoreLazySection>



        <StoreLazySection min-height="320px">
            <StoreHomeNewsletter :enabled="homeContent.newsletter_enabled !== false" />
        </StoreLazySection>



        <!-- SEO HTML block: split chunk but render eagerly so crawlers always see it. -->
        <StoreHomeSeoContent

            :categories="categories"

            :seo-html="homeContent.seo_html"

        />



        <StoreProductQuickAddSheet
            :open="sheetOpen"
            :product="sheetProduct"
            :adding="adding"
            @close="closeSheet"
            @select="onSheetSelect"
        />

    </StoreLayout>

</template>


